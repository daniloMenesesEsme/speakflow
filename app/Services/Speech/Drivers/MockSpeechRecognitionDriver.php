<?php

namespace App\Services\Speech\Drivers;

use App\Contracts\SpeechRecognitionContract;
use App\ValueObjects\PronunciationResult;

/**
 * Driver padrão — opera 100% offline sem dependências externas.
 *
 * Como não há acesso ao áudio real, simula as três métricas baseando-se
 * na transcrição textual fornecida pelo app mobile (STT local no device).
 *
 * Quando o app mobile envia { transcription: "..." }, este driver compara
 * o texto com o expected_text e deriva accuracy, fluency e confidence
 * através de heurísticas de similaridade textual.
 *
 * Substituição futura: trocar por AzureSpeechDriver, GoogleSpeechDriver
 * ou WhisperDriver sem alterar nenhum código do PronunciationAnalyzer.
 */
class MockSpeechRecognitionDriver implements SpeechRecognitionContract
{
    public function driverName(): string
    {
        return 'mock';
    }

    public function isAvailable(): bool
    {
        return true; // Sempre disponível (offline-first)
    }

    /**
     * Analisa via comparação textual.
     *
     * $options aceita:
     *   'transcription'  → texto detectado pelo STT local do dispositivo
     *   'audio_duration' → duração do áudio em segundos (para cálculo de fluência)
     *   'word_count'     → número de palavras esperadas (baseline de velocidade)
     */
    public function analyze(
        string $audioPath,
        string $expectedText,
        array $options = []
    ): PronunciationResult {
        $startMs = (int) round(microtime(true) * 1000);

        $transcription  = $options['transcription'] ?? '';
        $audioDuration  = (float) ($options['audio_duration'] ?? 0);
        $wordCount      = (int)   ($options['word_count'] ?? str_word_count($expectedText));

        $accuracy    = $this->computeAccuracy($transcription, $expectedText);
        $fluency     = $this->computeFluency($transcription, $expectedText, $audioDuration, $wordCount);
        $confidence  = $this->computeConfidence($transcription, $expectedText, $options);
        $phonemes    = $this->extractPhonemeScores($transcription, $expectedText);

        $processingMs = (int) round(microtime(true) * 1000) - $startMs;

        return new PronunciationResult(
            accuracy:       $accuracy,
            fluency:        $fluency,
            confidence:     $confidence,
            transcription:  $transcription,
            driver:         $this->driverName(),
            processingMs:   $processingMs,
            phonemeScores:  $phonemes,
            metadata:       [
                'audio_path'    => $audioPath,
                'audio_duration'=> $audioDuration,
                'method'        => 'text_similarity',
            ],
        );
    }

    // ─── Cálculo de Accuracy ─────────────────────────────────────────────────

    /**
     * Accuracy: precisão fonética estimada via similaridade textual combinada.
     * Levenshtein (60%) + similar_text (40%), com bônus por match exato.
     */
    private function computeAccuracy(string $transcription, string $expected): float
    {
        if (empty($transcription)) {
            return 0.0;
        }

        $t = $this->normalize($transcription);
        $e = $this->normalize($expected);

        if ($t === $e) {
            return 100.0;
        }

        $maxLen = max(mb_strlen($t), mb_strlen($e));
        $levenshteinScore = $maxLen > 0
            ? max(0, 1 - (levenshtein($t, $e) / $maxLen))
            : 0;

        similar_text($t, $e, $similarPct);
        $similarScore = $similarPct / 100;

        $raw = ($levenshteinScore * 0.60 + $similarScore * 0.40) * 100;

        // Bônus por palavras-chave corretas
        $keywordBonus = $this->keywordBonus($t, $e);

        return min(100.0, round($raw + $keywordBonus, 2));
    }

    // ─── Cálculo de Fluency ──────────────────────────────────────────────────

    /**
     * Fluency: estimada pela razão entre velocidade de fala e ritmo esperado.
     *
     * Velocidade ideal para inglês: 120–150 palavras por minuto.
     * Penaliza tanto fala muito lenta (< 80 wpm) quanto muito rápida (> 180 wpm).
     * Quando não há dados de duração, deriva do comprimento da transcrição.
     */
    private function computeFluency(
        string $transcription,
        string $expected,
        float $audioDuration,
        int $wordCount
    ): float {
        if (empty($transcription)) {
            return 0.0;
        }

        // Cobertura das palavras esperadas (penaliza palavras omitidas)
        $tWords = array_filter(explode(' ', $this->normalize($transcription)));
        $eWords = array_filter(explode(' ', $this->normalize($expected)));
        $coverage = count($eWords) > 0
            ? min(1.0, count($tWords) / count($eWords))
            : 1.0;

        // Velocidade de fala, se temos duração real
        $wpmScore = 1.0;
        if ($audioDuration > 0 && count($tWords) > 0) {
            $wpm = (count($tWords) / $audioDuration) * 60;
            $wpmScore = $this->wpmToScore($wpm);
        }

        $rawFluency = ($coverage * 0.55 + $wpmScore * 0.45) * 100;

        return min(100.0, round($rawFluency, 2));
    }

    // ─── Cálculo de Confidence ───────────────────────────────────────────────

    /**
     * Confidence: estimada pela completude e clareza da transcrição.
     *
     * Indicadores de baixa confiança:
     *  - transcrição muito curta vs. esperada (voz baixa/cortada)
     *  - muitas palavras desconhecidas vs. esperadas
     *  - score de confiança externo, se fornecido pelo STT do device
     */
    private function computeConfidence(
        string $transcription,
        string $expected,
        array $options
    ): float {
        if (empty($transcription)) {
            return 0.0;
        }

        // Se o app mobile enviou um confidence_score nativo do STT, usar direto
        if (isset($options['stt_confidence']) && is_numeric($options['stt_confidence'])) {
            $native = (float) $options['stt_confidence'];
            return min(100.0, max(0.0, $native * 100));
        }

        $t = $this->normalize($transcription);
        $e = $this->normalize($expected);

        // Proporção de comprimento (voz muito curta → baixa confiança)
        $lenRatio = mb_strlen($e) > 0
            ? min(1.0, mb_strlen($t) / mb_strlen($e))
            : 1.0;

        // Proporção de palavras comuns
        $tWords    = array_unique(array_filter(explode(' ', $t)));
        $eWords    = array_unique(array_filter(explode(' ', $e)));
        $common    = count(array_intersect($tWords, $eWords));
        $wordRatio = count($eWords) > 0 ? min(1.0, $common / count($eWords)) : 1.0;

        $raw = ($lenRatio * 0.40 + $wordRatio * 0.60) * 100;

        return min(100.0, round($raw, 2));
    }

    // ─── Fonemas simulados ───────────────────────────────────────────────────

    /**
     * Gera scores por "fonema" (baseado em bigramas de letras).
     * Substituído por scores reais ao usar driver externo.
     */
    private function extractPhonemeScores(string $transcription, string $expected): array
    {
        if (empty($transcription)) {
            return [];
        }

        $words    = explode(' ', $this->normalize($expected));
        $tWords   = explode(' ', $this->normalize($transcription));
        $scores   = [];

        foreach ($words as $i => $word) {
            if (strlen($word) < 2) {
                continue;
            }

            $tWord  = $tWords[$i] ?? '';
            $maxLen = max(strlen($word), strlen($tWord));
            $score  = $maxLen > 0
                ? max(0, 1 - (levenshtein($word, $tWord) / $maxLen))
                : 0;

            $scores[$word] = round($score * 100, 1);
        }

        return $scores;
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace("/['']/u", "'", $text);
        $text = preg_replace('/[^\w\s\']/u', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    private function keywordBonus(string $transcription, string $expected): float
    {
        $stopWords = ['a', 'an', 'the', 'i', 'to', 'of', 'in', 'is', 'it', 'and'];
        $keywords  = array_filter(
            explode(' ', $expected),
            fn ($w) => strlen($w) > 2 && !in_array($w, $stopWords)
        );

        if (empty($keywords)) {
            return 0.0;
        }

        $matched = count(array_filter(
            $keywords,
            fn ($kw) => str_contains($transcription, $kw)
        ));

        return ($matched / count($keywords)) * 8; // máximo 8 pontos de bônus
    }

    /**
     * Converte WPM (words per minute) em score 0–1.
     * Ideal: 120–150 wpm. Penaliza < 80 wpm e > 180 wpm.
     */
    private function wpmToScore(float $wpm): float
    {
        return match (true) {
            $wpm < 40   => 0.30,
            $wpm < 80   => 0.30 + ($wpm - 40) / 40 * 0.35,
            $wpm <= 150 => 0.65 + ($wpm - 80) / 70 * 0.35,
            $wpm <= 180 => 1.0 - ($wpm - 150) / 30 * 0.20,
            default     => max(0.20, 1.0 - ($wpm - 180) / 100 * 0.60),
        };
    }
}
