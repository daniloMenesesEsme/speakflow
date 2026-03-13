<?php

namespace App\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object imutável que representa o resultado de uma análise de pronúncia.
 *
 * As três métricas são independentes e combinadas num score composto:
 *
 *  accuracy    — Precisão fonética: quão corretos foram os sons produzidos.
 *                Detecta erros de fonemas específicos (ex: "th", vogais curtas).
 *
 *  fluency     — Fluência: naturalidade do ritmo, velocidade, prosódia e pausas.
 *                Alta = fala contínua e natural. Baixa = muitas pausas ou ritmo irregular.
 *
 *  confidence  — Confiança/clareza: volume, nitidez e projeção da voz.
 *                Alta = voz clara e bem projetada. Baixa = voz baixa, hesitante ou muffled.
 *
 *  composite_score — Média ponderada das três métricas:
 *                    accuracy × 0.50 + fluency × 0.30 + confidence × 0.20
 */
final class PronunciationResult
{
    // Pesos para o score composto
    public const WEIGHT_ACCURACY   = 0.50;
    public const WEIGHT_FLUENCY    = 0.30;
    public const WEIGHT_CONFIDENCE = 0.20;

    private readonly float $compositeScore;

    public function __construct(
        private readonly float   $accuracy,
        private readonly float   $fluency,
        private readonly float   $confidence,
        private readonly string  $transcription  = '',
        private readonly string  $driver         = 'mock',
        private readonly ?int    $processingMs   = null,
        private readonly array   $phonemeScores  = [],
        private readonly array   $metadata       = [],
    ) {
        $this->validate();

        $this->compositeScore = round(
            $this->accuracy    * self::WEIGHT_ACCURACY   +
            $this->fluency     * self::WEIGHT_FLUENCY     +
            $this->confidence  * self::WEIGHT_CONFIDENCE,
            2
        );
    }

    // ─── Factory ────────────────────────────────────────────────────────────

    /**
     * Cria a partir de um array (útil ao receber resposta de API externa).
     */
    public static function fromArray(array $data): self
    {
        return new self(
            accuracy:       (float) ($data['accuracy']      ?? 0),
            fluency:        (float) ($data['fluency']        ?? 0),
            confidence:     (float) ($data['confidence']     ?? 0),
            transcription:  (string) ($data['transcription'] ?? ''),
            driver:         (string) ($data['driver']        ?? 'mock'),
            processingMs:   isset($data['processing_time_ms']) ? (int) $data['processing_time_ms'] : null,
            phonemeScores:  (array) ($data['phoneme_scores'] ?? []),
            metadata:       (array) ($data['metadata']       ?? []),
        );
    }

    /**
     * Resultado de fallback quando o driver falha — pontua zero.
     */
    public static function failed(string $driver = 'mock', string $reason = ''): self
    {
        return new self(
            accuracy:   0,
            fluency:    0,
            confidence: 0,
            driver:     $driver,
            metadata:   ['failed' => true, 'reason' => $reason],
        );
    }

    // ─── Getters ────────────────────────────────────────────────────────────

    public function accuracy(): float        { return $this->accuracy; }
    public function fluency(): float         { return $this->fluency; }
    public function confidence(): float      { return $this->confidence; }
    public function compositeScore(): float  { return $this->compositeScore; }
    public function transcription(): string  { return $this->transcription; }
    public function driver(): string         { return $this->driver; }
    public function processingMs(): ?int     { return $this->processingMs; }
    public function phonemeScores(): array   { return $this->phonemeScores; }
    public function metadata(): array        { return $this->metadata; }

    // ─── Helpers semânticos ─────────────────────────────────────────────────

    public function isExcellent(): bool  { return $this->compositeScore >= 90; }
    public function isGood(): bool       { return $this->compositeScore >= 75; }
    public function isPassing(): bool    { return $this->compositeScore >= 60; }
    public function isFailed(): bool     { return (bool) ($this->metadata['failed'] ?? false); }

    public function weakestMetric(): string
    {
        $metrics = [
            'accuracy'   => $this->accuracy,
            'fluency'    => $this->fluency,
            'confidence' => $this->confidence,
        ];

        return array_key_first(array_filter(
            $metrics,
            fn ($v) => $v === min($metrics)
        ));
    }

    public function grade(): string
    {
        return match (true) {
            $this->compositeScore >= 90 => 'A',
            $this->compositeScore >= 80 => 'B',
            $this->compositeScore >= 70 => 'C',
            $this->compositeScore >= 60 => 'D',
            default                     => 'F',
        };
    }

    /**
     * Retorna os dados como array pronto para persistência no banco.
     */
    public function toStorageArray(): array
    {
        return [
            'score'               => $this->compositeScore,
            'accuracy'            => $this->accuracy,
            'fluency'             => $this->fluency,
            'confidence'          => $this->confidence,
            'transcription'       => $this->transcription ?: null,
            'driver'              => $this->driver,
            'processing_time_ms'  => $this->processingMs,
            'phoneme_scores'      => !empty($this->phonemeScores) ? $this->phonemeScores : null,
        ];
    }

    public function toArray(): array
    {
        return array_merge($this->toStorageArray(), [
            'composite_score' => $this->compositeScore,
            'grade'           => $this->grade(),
            'weakest_metric'  => $this->weakestMetric(),
            'metadata'        => $this->metadata,
        ]);
    }

    // ─── Validação ──────────────────────────────────────────────────────────

    private function validate(): void
    {
        foreach (['accuracy' => $this->accuracy, 'fluency' => $this->fluency, 'confidence' => $this->confidence] as $name => $value) {
            if ($value < 0 || $value > 100) {
                throw new InvalidArgumentException("PronunciationResult: '{$name}' deve ser entre 0 e 100. Recebido: {$value}");
            }
        }
    }
}
