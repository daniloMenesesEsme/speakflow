<?php

namespace App\Services;

use App\Contracts\SpeechRecognitionContract;
use App\Models\Phrase;
use App\Models\PronunciationScore;
use App\Models\User;
use App\Services\Speech\Drivers\MockSpeechRecognitionDriver;
use App\ValueObjects\PronunciationResult;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * PronunciationAnalyzer — Serviço de análise de pronúncia do SpeakFlow.
 *
 * Arquitetura modular:
 *   - Usa um SpeechRecognitionContract intercambiável (driver pattern).
 *   - Padrão: MockSpeechRecognitionDriver (offline, sem dependências externas).
 *   - Troca para Azure/Google/Whisper em tempo de execução via binding no AppServiceProvider.
 *
 * Fluxo principal:
 *   analyzePronunciation()  → driver→analyze() → PronunciationResult
 *   calculatePronunciationScore()  → pesos das 3 métricas → score composto
 *   savePronunciationScore()  → persiste na tabela pronunciation_scores
 */
class PronunciationAnalyzer
{
    // ─── Thresholds de classificação ────────────────────────────────────────
    private const EXCELLENT = 90;
    private const GOOD      = 75;
    private const PASS      = 60;

    // ─── Thresholds por métrica (para feedback contextual) ──────────────────
    private const ACCURACY_LOW    = 60;
    private const FLUENCY_LOW     = 60;
    private const CONFIDENCE_LOW  = 55;
    private const SPEED_SLOW      = 65; // fluency baixa e transcription curta
    private const SPEED_FAST      = 65; // fluency baixa e transcription longa

    public function __construct(
        private readonly SpeechRecognitionContract $driver
    ) {}

    // ══════════════════════════════════════════════════════════════════════════
    //  1. ANALISAR PRONÚNCIA
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Ponto de entrada principal. Orquestra análise completa:
     * recebe o áudio, chama o driver, calcula score composto e gera feedback.
     *
     * @param  string  $audioPath    Caminho local ou URL do arquivo de áudio.
     * @param  string  $expectedText Texto que o usuário deveria pronunciar.
     * @param  array   $options      Opções extras:
     *   - 'transcription'   (string)  Texto detectado pelo STT do device
     *   - 'stt_confidence'  (float)   Confiança nativa do STT (0–1)
     *   - 'audio_duration'  (float)   Duração do áudio em segundos
     *   - 'word_count'      (int)     Número de palavras esperadas
     *
     * @return array Resultado completo com métricas, score, grade e feedback.
     */
    public function analyzePronunciation(
        string $audioPath,
        string $expectedText,
        array $options = []
    ): array {
        $driver = $this->resolveDriver();

        try {
            $result = $driver->analyze($audioPath, $expectedText, $options);
        } catch (\Throwable $e) {
            $result = PronunciationResult::failed($driver->driverName(), $e->getMessage());
        }

        // Fallback: se driver externo falhou, tenta Mock
        if ($result->isFailed() && !($driver instanceof MockSpeechRecognitionDriver)) {
            $fallback = new MockSpeechRecognitionDriver();
            $result   = $fallback->analyze($audioPath, $expectedText, $options);
        }

        $feedback = $this->generateDetailedFeedback($result, $expectedText);

        return [
            'metrics'     => [
                'accuracy'        => $result->accuracy(),
                'fluency'         => $result->fluency(),
                'confidence'      => $result->confidence(),
                'composite_score' => $result->compositeScore(),
            ],
            'grade'        => $result->grade(),
            'feedback'     => $feedback,
            'transcription'=> $result->transcription(),
            'phoneme_scores'=> $result->phonemeScores(),
            'driver'       => $result->driver(),
            'processing_ms'=> $result->processingMs(),
            'raw_result'   => $result,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  2. CALCULAR SCORE DE PRONÚNCIA
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Calcula o score composto ponderado das três métricas.
     * Pode ser chamado isoladamente quando as métricas já foram obtidas.
     *
     * Pesos: accuracy × 0.50 + fluency × 0.30 + confidence × 0.20
     *
     * @param  float  $accuracy    Precisão fonética (0–100)
     * @param  float  $fluency     Fluência e ritmo (0–100)
     * @param  float  $confidence  Clareza e confiança (0–100)
     * @param  bool   $applyBonuses Aplica bônus por excelência em métricas individuais
     *
     * @return array{score: float, grade: string, breakdown: array}
     */
    public function calculatePronunciationScore(
        float $accuracy,
        float $fluency,
        float $confidence,
        bool $applyBonuses = true
    ): array {
        $accuracy   = $this->clamp($accuracy);
        $fluency    = $this->clamp($fluency);
        $confidence = $this->clamp($confidence);

        $composite = round(
            $accuracy   * PronunciationResult::WEIGHT_ACCURACY   +
            $fluency    * PronunciationResult::WEIGHT_FLUENCY     +
            $confidence * PronunciationResult::WEIGHT_CONFIDENCE,
            2
        );

        // Bônus de excelência: +2 pontos por métrica excelente (máx 6)
        if ($applyBonuses) {
            $excellenceBonus  = 0;
            $excellenceBonus += $accuracy   >= self::EXCELLENT ? 2 : 0;
            $excellenceBonus += $fluency    >= self::EXCELLENT ? 2 : 0;
            $excellenceBonus += $confidence >= self::EXCELLENT ? 2 : 0;
            $composite = min(100, $composite + $excellenceBonus);
        }

        $grade  = $this->toGrade($composite);
        $result = new PronunciationResult($accuracy, $fluency, $confidence);

        return [
            'score'     => $composite,
            'grade'     => $grade,
            'breakdown' => [
                'accuracy'          => $accuracy,
                'accuracy_weight'   => PronunciationResult::WEIGHT_ACCURACY,
                'accuracy_weighted' => round($accuracy * PronunciationResult::WEIGHT_ACCURACY, 2),
                'fluency'           => $fluency,
                'fluency_weight'    => PronunciationResult::WEIGHT_FLUENCY,
                'fluency_weighted'  => round($fluency * PronunciationResult::WEIGHT_FLUENCY, 2),
                'confidence'        => $confidence,
                'confidence_weight' => PronunciationResult::WEIGHT_CONFIDENCE,
                'confidence_weighted'=> round($confidence * PronunciationResult::WEIGHT_CONFIDENCE, 2),
            ],
            'weakest_metric' => $result->weakestMetric(),
            'all_excellent'  => $composite >= self::EXCELLENT,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  3. SALVAR PONTUAÇÃO
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Persiste a análise de pronúncia na tabela pronunciation_scores.
     *
     * @param  User               $user     Usuário que realizou a tentativa
     * @param  Phrase             $phrase   Frase que foi pronunciada
     * @param  PronunciationResult $result  Resultado vindo do analyzePronunciation()
     *
     * @return PronunciationScore Registro persistido
     */
    public function savePronunciationScore(
        User $user,
        Phrase $phrase,
        PronunciationResult $result
    ): PronunciationScore {
        $feedback = $this->generateDetailedFeedback($result, $phrase->english_text);

        return PronunciationScore::create(array_merge(
            $result->toStorageArray(),
            [
                'user_id'   => $user->id,
                'phrase_id' => $phrase->id,
                'feedback'  => $feedback['summary'],
            ]
        ));
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  FEEDBACK CONTEXTUAL
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Gera feedback rico baseado na combinação das três métricas.
     * Os feedbacks são contextuais — a mensagem muda dependendo de qual
     * combinação de métricas está fraca ou forte.
     *
     * @return array{summary: string, tips: array, encouragement: string}
     */
    public function generateDetailedFeedback(
        PronunciationResult $result,
        string $expectedText = ''
    ): array {
        $acc  = $result->accuracy();
        $flu  = $result->fluency();
        $con  = $result->confidence();
        $comp = $result->compositeScore();

        $summary      = $this->buildSummaryFeedback($acc, $flu, $con, $comp);
        $tips         = $this->buildTips($acc, $flu, $con, $result->phonemeScores(), $expectedText);
        $encouragement= $this->buildEncouragement($comp, $result->grade());

        return [
            'summary'       => $summary,
            'tips'          => $tips,
            'encouragement' => $encouragement,
            'metrics_labels'=> $this->metricsLabels($acc, $flu, $con),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Mensagem principal baseada em combinações de métricas
    // ──────────────────────────────────────────────────────────────────────────

    private function buildSummaryFeedback(
        float $acc,
        float $flu,
        float $con,
        float $comp
    ): string {
        // Tudo excelente
        if ($acc >= self::EXCELLENT && $flu >= self::EXCELLENT && $con >= self::EXCELLENT) {
            return 'Pronúncia nativa! Você dominou completamente esta frase.';
        }

        // Tudo bom
        if ($acc >= self::GOOD && $flu >= self::GOOD && $con >= self::GOOD) {
            return 'Ótima pronúncia! Continue praticando para alcançar a perfeição.';
        }

        // Accuracy boa, fluency baixa → fala correta mas irregular
        if ($acc >= self::GOOD && $flu < self::FLUENCY_LOW) {
            return 'Boa pronúncia, mas tente falar um pouco mais devagar e com ritmo mais natural.';
        }

        // Fluency boa, accuracy baixa → fala fluente mas com sons errados
        if ($flu >= self::GOOD && $acc < self::ACCURACY_LOW) {
            return 'Você fala com boa fluência, mas precisa melhorar a precisão dos sons.';
        }

        // Confidence baixa → voz hesitante
        if ($con < self::CONFIDENCE_LOW && $acc >= self::PASS) {
            return 'Boa pronúncia! Tente falar com mais confiança e projetar melhor a voz.';
        }

        // Accuracy baixa e fluency baixa
        if ($acc < self::ACCURACY_LOW && $flu < self::FLUENCY_LOW) {
            return 'Pratique mais! Ouça o áudio modelo e tente imitar o ritmo e os sons.';
        }

        // Accuracy baixa isolada
        if ($acc < self::ACCURACY_LOW) {
            return 'Foque na pronúncia correta dos sons. Ouça o áudio com atenção antes de falar.';
        }

        // Fluency baixa isolada
        if ($flu < self::FLUENCY_LOW) {
            return 'Pronúncia aceitável! Trabalhe no ritmo para soar mais natural.';
        }

        // Score composto entre 60–75
        if ($comp >= self::PASS) {
            return 'Pronunciou bem! Há espaço para melhorar. Pratique mais algumas vezes.';
        }

        return 'Continue praticando! Repita em voz alta ouvindo o áudio modelo.';
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Dicas específicas por métrica e fonemas fracos
    // ──────────────────────────────────────────────────────────────────────────

    private function buildTips(
        float $acc,
        float $flu,
        float $con,
        array $phonemeScores,
        string $expectedText
    ): array {
        $tips = [];

        if ($acc < self::ACCURACY_LOW) {
            $weakPhonemes = $this->extractWeakPhonemes($phonemeScores);
            if ($weakPhonemes) {
                $tips[] = "Preste atenção nos sons: " . implode(', ', $weakPhonemes) . '.';
            } else {
                $tips[] = 'Ouça o áudio modelo várias vezes antes de repetir.';
            }
        }

        if ($flu < self::FLUENCY_LOW) {
            $expectedWords = str_word_count($expectedText);
            if ($expectedWords > 5) {
                $tips[] = 'Divida a frase em partes menores e pratique cada parte separadamente.';
            } else {
                $tips[] = 'Fale em um ritmo constante, sem pausas longas entre as palavras.';
            }
        }

        if ($con < self::CONFIDENCE_LOW) {
            $tips[] = 'Fale em voz alta e com confiança. Projete sua voz como se estivesse em uma conversa real.';
        }

        if ($acc >= self::EXCELLENT) {
            $tips[] = 'Sua precisão fonética está ótima! Tente agora frases mais longas.';
        }

        if ($flu >= self::EXCELLENT) {
            $tips[] = 'Fluidez excelente! Seu ritmo está natural e próximo ao de um falante nativo.';
        }

        return array_values(array_unique($tips));
    }

    private function buildEncouragement(float $score, string $grade): string
    {
        return match ($grade) {
            'A' => 'Fantástico! Você está falando como um nativo! 🎉',
            'B' => 'Muito bem! Você está quase lá! 💪',
            'C' => 'Bom progresso! Mais algumas práticas e você vai dominar. 📈',
            'D' => 'Continue tentando! Cada prática conta. 🔄',
            default => 'Não desista! Todo especialista um dia foi iniciante. 🌱',
        };
    }

    private function metricsLabels(float $acc, float $flu, float $con): array
    {
        return [
            'accuracy'   => ['value' => $acc,  'label' => $this->metricLabel($acc),  'name' => 'Precisão'],
            'fluency'    => ['value' => $flu,  'label' => $this->metricLabel($flu),  'name' => 'Fluência'],
            'confidence' => ['value' => $con,  'label' => $this->metricLabel($con),  'name' => 'Confiança'],
        ];
    }

    private function metricLabel(float $value): string
    {
        return match (true) {
            $value >= self::EXCELLENT => 'Excelente',
            $value >= self::GOOD      => 'Bom',
            $value >= self::PASS      => 'Regular',
            default                   => 'Precisa melhorar',
        };
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  ANÁLISE HISTÓRICA
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Análise completa do histórico de pronúncia do usuário,
     * incluindo evolução das três métricas ao longo do tempo.
     */
    public function analyzeUserPronunciation(User $user): array
    {
        $scores = PronunciationScore::where('user_id', $user->id)
            ->with('phrase')
            ->orderBy('created_at', 'desc')
            ->get();

        if ($scores->isEmpty()) {
            return $this->emptyAnalysis();
        }

        $avgScore      = round($scores->avg('score'), 2);
        $avgAccuracy   = round($scores->avg('accuracy'), 2);
        $avgFluency    = round($scores->avg('fluency'), 2);
        $avgConfidence = round($scores->avg('confidence'), 2);

        return [
            'overall' => [
                'average_score' => $avgScore,
                'grade'         => $this->toGrade($avgScore),
                'total_attempts'=> $scores->count(),
                'improvement'   => $this->calculateImprovement($scores),
            ],
            'metrics_average' => [
                'accuracy'   => $avgAccuracy,
                'fluency'    => $avgFluency,
                'confidence' => $avgConfidence,
            ],
            'weak_areas'   => $this->identifyWeakAreas($scores),
            'strong_areas' => $this->identifyStrongAreas($scores),
            'recent_scores'=> $scores->take(10)->map(fn ($s) => [
                'phrase'     => $s->phrase->english_text ?? '',
                'score'      => $s->score,
                'accuracy'   => $s->accuracy,
                'fluency'    => $s->fluency,
                'confidence' => $s->confidence,
                'grade'      => $s->grade,
                'created_at' => $s->created_at->toISOString(),
            ])->values(),
            'metrics_trend'  => $this->calculateMetricsTrend($scores),
            'recommendation' => $this->generateRecommendation($avgScore, $avgAccuracy, $avgFluency, $avgConfidence),
        ];
    }

    public function getPhraseProgress(User $user, Phrase $phrase): array
    {
        $scores = PronunciationScore::where('user_id', $user->id)
            ->where('phrase_id', $phrase->id)
            ->orderBy('created_at')
            ->get();

        if ($scores->isEmpty()) {
            return ['phrase_id' => $phrase->id, 'attempts' => 0,
                    'best_score' => null, 'latest_score' => null,
                    'average_score' => null, 'improvement' => null];
        }

        return [
            'phrase_id'      => $phrase->id,
            'attempts'       => $scores->count(),
            'best_score'     => round($scores->max('score'), 2),
            'latest_score'   => round($scores->last()->score, 2),
            'average_score'  => round($scores->avg('score'), 2),
            'improvement'    => $this->calculateImprovement($scores),
            'latest_metrics' => [
                'accuracy'   => $scores->last()->accuracy,
                'fluency'    => $scores->last()->fluency,
                'confidence' => $scores->last()->confidence,
            ],
            'history' => $scores->map(fn ($s) => [
                'score'      => $s->score,
                'accuracy'   => $s->accuracy,
                'fluency'    => $s->fluency,
                'confidence' => $s->confidence,
                'grade'      => $s->grade,
                'created_at' => $s->created_at->toISOString(),
            ])->values(),
        ];
    }

    public function getWeeklyReport(User $user): array
    {
        $startOfWeek  = Carbon::now()->startOfWeek();
        $weeklyScores = PronunciationScore::where('user_id', $user->id)
            ->where('created_at', '>=', $startOfWeek)
            ->get();

        $dailyBreakdown = [];
        for ($i = 0; $i < 7; $i++) {
            $day       = $startOfWeek->copy()->addDays($i);
            $dayScores = $weeklyScores->filter(
                fn ($s) => Carbon::parse($s->created_at)->isSameDay($day)
            );

            $dailyBreakdown[] = [
                'date'       => $day->toDateString(),
                'day_name'   => $day->locale('pt_BR')->isoFormat('ddd'),
                'attempts'   => $dayScores->count(),
                'avg_score'  => $dayScores->isNotEmpty() ? round($dayScores->avg('score'), 2) : null,
                'avg_accuracy'   => $dayScores->isNotEmpty() ? round($dayScores->avg('accuracy'), 2) : null,
                'avg_fluency'    => $dayScores->isNotEmpty() ? round($dayScores->avg('fluency'), 2) : null,
                'avg_confidence' => $dayScores->isNotEmpty() ? round($dayScores->avg('confidence'), 2) : null,
            ];
        }

        return [
            'week_start'      => $startOfWeek->toDateString(),
            'total_attempts'  => $weeklyScores->count(),
            'avg_score'       => $weeklyScores->isNotEmpty() ? round($weeklyScores->avg('score'), 2) : null,
            'avg_accuracy'    => $weeklyScores->isNotEmpty() ? round($weeklyScores->avg('accuracy'), 2) : null,
            'avg_fluency'     => $weeklyScores->isNotEmpty() ? round($weeklyScores->avg('fluency'), 2) : null,
            'avg_confidence'  => $weeklyScores->isNotEmpty() ? round($weeklyScores->avg('confidence'), 2) : null,
            'daily_breakdown' => $dailyBreakdown,
            'best_day'        => $this->getBestDay($dailyBreakdown),
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  PRIVADOS — HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    private function resolveDriver(): SpeechRecognitionContract
    {
        if ($this->driver->isAvailable()) {
            return $this->driver;
        }

        return new MockSpeechRecognitionDriver();
    }

    private function calculateMetricsTrend(Collection $scores): array
    {
        if ($scores->count() < 3) {
            return ['accuracy' => null, 'fluency' => null, 'confidence' => null];
        }

        $half = (int) ceil($scores->count() / 2);
        $first  = $scores->reverse()->take($half); // mais antigas
        $second = $scores->take($half);            // mais recentes

        return [
            'accuracy'   => round($second->avg('accuracy')   - $first->avg('accuracy'),   2),
            'fluency'    => round($second->avg('fluency')     - $first->avg('fluency'),    2),
            'confidence' => round($second->avg('confidence')  - $first->avg('confidence'), 2),
        ];
    }

    private function identifyWeakAreas(Collection $scores): array
    {
        return $scores->filter(fn ($s) => $s->score < self::PASS)
            ->groupBy('phrase_id')
            ->map(fn ($g) => [
                'phrase'         => $g->first()->phrase->english_text ?? '',
                'avg_score'      => round($g->avg('score'), 2),
                'avg_accuracy'   => round($g->avg('accuracy'), 2),
                'avg_fluency'    => round($g->avg('fluency'), 2),
                'avg_confidence' => round($g->avg('confidence'), 2),
                'attempts'       => $g->count(),
            ])
            ->sortBy('avg_score')
            ->take(5)
            ->values()
            ->toArray();
    }

    private function identifyStrongAreas(Collection $scores): array
    {
        return $scores->filter(fn ($s) => $s->score >= self::GOOD)
            ->groupBy('phrase_id')
            ->map(fn ($g) => [
                'phrase'    => $g->first()->phrase->english_text ?? '',
                'avg_score' => round($g->avg('score'), 2),
                'attempts'  => $g->count(),
            ])
            ->sortByDesc('avg_score')
            ->take(5)
            ->values()
            ->toArray();
    }

    private function calculateImprovement(Collection $scores): float
    {
        if ($scores->count() < 2) {
            return 0.0;
        }

        $half      = (int) ceil($scores->count() / 2);
        $firstAvg  = $scores->take($half)->avg('score');
        $secondAvg = $scores->skip($half)->avg('score');

        return round($secondAvg - $firstAvg, 2);
    }

    private function generateRecommendation(
        float $avg,
        float $accuracy,
        float $fluency,
        float $confidence
    ): string {
        if ($avg >= self::EXCELLENT) {
            return 'Pronúncia excelente! Passe para frases mais complexas e diálogos avançados.';
        }

        $weakest = match (min($accuracy, $fluency, $confidence)) {
            $accuracy   => 'precisão fonética — pratique sons difíceis como "th", "r" e vogais curtas',
            $fluency    => 'fluência — leia textos em voz alta diariamente para melhorar o ritmo',
            $confidence => 'confiança — grave-se falando e ouça depois para ganhar familiaridade',
            default     => 'pronúncia geral',
        };

        return "Foque em melhorar sua {$weakest}.";
    }

    private function extractWeakPhonemes(array $phonemeScores): array
    {
        return collect($phonemeScores)
            ->filter(fn ($score) => $score < 60)
            ->keys()
            ->take(3)
            ->toArray();
    }

    private function getBestDay(array $dailyBreakdown): ?string
    {
        $best = collect($dailyBreakdown)
            ->filter(fn ($d) => $d['avg_score'] !== null)
            ->sortByDesc('avg_score')
            ->first();

        return $best ? $best['date'] : null;
    }

    private function toGrade(float $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default      => 'F',
        };
    }

    private function clamp(float $value): float
    {
        return min(100.0, max(0.0, $value));
    }

    private function emptyAnalysis(): array
    {
        return [
            'overall'         => ['average_score' => null, 'grade' => null, 'total_attempts' => 0, 'improvement' => 0],
            'metrics_average' => ['accuracy' => null, 'fluency' => null, 'confidence' => null],
            'weak_areas'      => [],
            'strong_areas'    => [],
            'recent_scores'   => [],
            'metrics_trend'   => ['accuracy' => null, 'fluency' => null, 'confidence' => null],
            'recommendation'  => 'Comece a praticar pronúncia para ver sua análise completa aqui!',
        ];
    }
}
