<?php

namespace App\Services;

use App\Models\AiConversation;
use App\Models\AiCorrection;
use App\Models\ConversationTopic;
use App\Models\Exercise;
use App\Models\Lesson;
use App\Models\User;
use App\Models\UserExerciseAttempt;
use App\Models\UserLessonProgress;
use App\Models\UserWeeklyXp;
use App\ValueObjects\CefrLevel;
use Illuminate\Support\Facades\DB;

class AdaptiveTutorService
{
    // ─── Pesos para o cálculo de nível adaptativo ─────────────────────────────

    private const WEIGHT_XP        = 0.50; // contribuição do XP no score final
    private const WEIGHT_LESSONS   = 0.25; // contribuição das lições concluídas
    private const WEIGHT_ACCURACY  = 0.25; // contribuição da taxa de acerto

    // ─── Limiares de score para cada nível ────────────────────────────────────
    // Score normalizado de 0–100 por componente

    private const LEVEL_THRESHOLDS = [
        'A1' => 0,
        'A2' => 20,
        'B1' => 45,
        'B2' => 70,
        'C1' => 88,
        'C2' => 96,
    ];

    // ─── Mínimo de lições concluídas esperado por nível ───────────────────────

    private const LESSONS_EXPECTED = [
        'A1' => 0,
        'A2' => 5,
        'B1' => 15,
        'B2' => 30,
        'C1' => 55,
        'C2' => 90,
    ];

    // ─── Constructor ─────────────────────────────────────────────────────────

    public function __construct(
        private LearningAnalyticsService $analytics,
    ) {
    }

    // ─── API pública ─────────────────────────────────────────────────────────

    /**
     * Retorna o plano adaptativo completo para exibir no app.
     */
    public function getAdaptivePlan(User $user): array
    {
        $level              = $this->getUserLevel($user);
        $grammarFocus       = $this->getGrammarFocus($user);
        $recommendedTopics  = $this->getRecommendedTopics($user);
        $exercises          = $this->getRecommendedExercises($user, $level);
        $weeklyInsight      = $this->getWeeklyInsight($user);
        $studyTips          = $this->getStudyTips($user, $level, $grammarFocus);

        return [
            'level'                  => $level,
            'recommended_topics'     => $recommendedTopics,
            'grammar_focus'          => $grammarFocus,
            'recommended_exercises'  => $exercises,
            'weekly_insight'         => $weeklyInsight,
            'study_tips'             => $studyTips,
            'generated_at'           => now()->toISOString(),
        ];
    }

    /**
     * Determina o nível CEFR adaptativo do usuário usando 3 dimensões:
     *   - XP acumulado (50% do peso)
     *   - Lições concluídas (25%)
     *   - Taxa de acerto em exercícios (25%)
     *
     * O nível é suavizado: o usuário só avança se todas as dimensões
     * indicarem maturidade suficiente para o novo nível.
     */
    public function getUserLevel(User $user): array
    {
        $userId = $user->id;

        // ── Dimensão 1: XP → score 0–100 usando thresholds CEFR ────────────────
        $totalXp       = (int) ($user->total_xp ?? 0);
        $cefrFromXp    = CefrLevel::fromXp($totalXp);
        $xpScore       = $this->normalizeXpScore($totalXp);

        // ── Dimensão 2: Lições concluídas → score 0–100 ─────────────────────────
        $lessonsCompleted = UserLessonProgress::where('user_id', $userId)
            ->where('completed', true)
            ->count();
        $lessonScore = $this->normalizeLessonScore($lessonsCompleted);

        // ── Dimensão 3: Taxa de acerto → score 0–100 ────────────────────────────
        $totalAttempts  = UserExerciseAttempt::where('user_id', $userId)->count();
        $correctAnswers = UserExerciseAttempt::where('user_id', $userId)
            ->where('correct', true)
            ->count();
        $accuracyRate = $totalAttempts > 0
            ? round(($correctAnswers / $totalAttempts) * 100, 1)
            : 0.0;

        // ── Score composto ────────────────────────────────────────────────────────
        $compositeScore = round(
            ($xpScore * self::WEIGHT_XP) +
            ($lessonScore * self::WEIGHT_LESSONS) +
            ($accuracyRate * self::WEIGHT_ACCURACY)
        , 2);

        // ── Nível pelo score composto ─────────────────────────────────────────────
        $adaptiveLevel = 'A1';
        foreach (self::LEVEL_THRESHOLDS as $lvl => $minScore) {
            if ($compositeScore >= $minScore) {
                $adaptiveLevel = $lvl;
            }
        }

        $cefr        = CefrLevel::fromCode($adaptiveLevel);
        $nextLevel   = $cefr->next();

        // XP necessário para o próximo threshold de nível adaptativo
        $nextThreshold = array_values(array_filter(
            array_keys(self::LEVEL_THRESHOLDS),
            fn ($lvl) => self::LEVEL_THRESHOLDS[$lvl] > ($compositeScore ?? 0)
        ));

        return [
            'code'              => $adaptiveLevel,
            'description'       => $cefr->description(),
            'composite_score'   => $compositeScore,
            'next_level'        => $nextLevel?->code(),
            'xp_to_next_cefr'   => $cefr->xpToNextLevel($totalXp),
            'progress_percent'  => $cefr->progressPercentage($totalXp),
            'dimensions'        => [
                'xp_score'       => $xpScore,
                'lesson_score'   => $lessonScore,
                'accuracy_score' => $accuracyRate,
            ],
            'stats' => [
                'total_xp'         => $totalXp,
                'lessons_completed'=> $lessonsCompleted,
                'accuracy_rate'    => $accuracyRate,
                'cefr_from_xp'     => $cefrFromXp->code(),
            ],
        ];
    }

    /**
     * Retorna os 5 erros gramaticais mais frequentes do usuário
     * com sugestões de estudo baseadas no padrão de erro.
     */
    public function getGrammarFocus(User $user): array
    {
        $corrections = AiCorrection::where('user_id', $user->id)
            ->select(
                'explanation',
                DB::raw('COUNT(*) as occurrences'),
                DB::raw('MAX(created_at) as last_seen'),
                DB::raw('MAX(original_text) as example_original'),
                DB::raw('MAX(corrected_text) as example_corrected'),
            )
            ->groupBy('explanation')
            ->orderByDesc('occurrences')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'explanation'       => $row->explanation,
                'occurrences'       => (int) $row->occurrences,
                'last_seen'         => $row->last_seen,
                'example'           => [
                    'original'  => $row->example_original,
                    'corrected' => $row->example_corrected,
                ],
                'study_suggestion'  => $this->buildStudySuggestion($row->explanation),
            ])
            ->toArray();

        // Métricas de evolução gramática (reutilizando LearningAnalyticsService)
        $learningStats = $this->analytics->getLearningStats($user);

        return [
            'top_errors'          => $corrections,
            'total_corrections'   => $learningStats['total_corrections'],
            'grammar_trend'       => $learningStats['grammar_trend'],
            'corrections_this_week' => $learningStats['corrections_this_week'],
            'has_errors'          => count($corrections) > 0,
        ];
    }

    /**
     * Identifica tópicos de conversa pouco praticados pelo usuário
     * dentro do seu nível atual, priorizando os que ele ainda não tocou.
     */
    public function getRecommendedTopics(User $user): array
    {
        $levelData = $this->getUserLevel($user);
        $userLevel = $levelData['code'];

        // Tópicos já praticados pelo usuário (com topic_id)
        $practicedTopicIds = AiConversation::where('user_id', $user->id)
            ->whereNotNull('topic_id')
            ->pluck('topic_id')
            ->unique()
            ->toArray();

        // Tópicos disponíveis para o nível do usuário, ordenados por prioridade
        $available = ConversationTopic::active()
            ->forLevel($userLevel)
            ->orderBy('level')
            ->orderBy('id')
            ->get();

        $notPracticed = [];
        $lowPractice  = [];

        foreach ($available as $topic) {
            $conversationCount = AiConversation::where('user_id', $user->id)
                ->where('topic_id', $topic->id)
                ->count();

            $entry = [
                'id'                 => $topic->id,
                'title'              => $topic->title,
                'description'        => $topic->description,
                'level'              => $topic->level,
                'icon'               => $topic->icon,
                'conversations_done' => $conversationCount,
                'is_new'             => $conversationCount === 0,
            ];

            if ($conversationCount === 0) {
                $notPracticed[] = $entry;
            } elseif ($conversationCount < 3) {
                $lowPractice[] = $entry;
            }
        }

        // Retorna até 5 tópicos: novos primeiro, depois poucos praticados
        $recommended = array_slice(
            array_merge($notPracticed, $lowPractice),
            0,
            5
        );

        return [
            'recommended'       => $recommended,
            'total_available'   => $available->count(),
            'total_practiced'   => count($practicedTopicIds),
            'total_not_started' => count($notPracticed),
            'user_level'        => $userLevel,
        ];
    }

    /**
     * Sugere exercícios do nível do usuário que ele ainda não respondeu
     * corretamente, ou que têm baixa taxa de acerto.
     *
     * @return array{count: int, exercises: array, lesson_suggestion: array|null}
     */
    public function getRecommendedExercises(User $user, ?array $levelData = null): array
    {
        $level     = $levelData ?? $this->getUserLevel($user);
        $userLevel = $level['code'];

        // Exercícios já respondidos corretamente
        $masteredIds = UserExerciseAttempt::where('user_id', $user->id)
            ->where('correct', true)
            ->pluck('exercise_id')
            ->unique()
            ->toArray();

        // Exercícios disponíveis no nível do usuário ainda não dominados
        $pending = Exercise::with('lesson')
            ->whereHas('lesson', fn ($q) => $q->where('level', $userLevel)->where('is_active', true))
            ->whereNotIn('id', $masteredIds)
            ->orderBy('lesson_id')
            ->orderBy('order')
            ->limit(10)
            ->get();

        // Também busca exercícios de nível inferior com baixa taxa de acerto
        // Exercícios tentados 2+ vezes com menos de 50% de acerto
        $lowAccuracyExercises = UserExerciseAttempt::where('user_id', $user->id)
            ->select(
                'exercise_id',
                DB::raw('COUNT(*) as attempts'),
                DB::raw('SUM(CASE WHEN correct = true THEN 1 ELSE 0 END) as correct_count'),
            )
            ->groupBy('exercise_id')
            ->havingRaw('COUNT(*) >= 2')
            ->havingRaw('SUM(CASE WHEN correct = true THEN 1 ELSE 0 END) * 1.0 / COUNT(*) < 0.5')
            ->pluck('exercise_id')
            ->toArray();

        // Lição sugerida: a mais próxima não completada no nível
        $completedLessonIds = UserLessonProgress::where('user_id', $user->id)
            ->where('completed', true)
            ->pluck('lesson_id')
            ->toArray();

        $nextLesson = Lesson::where('level', $userLevel)
            ->where('is_active', true)
            ->whereNotIn('id', $completedLessonIds)
            ->orderBy('order')
            ->first();

        return [
            'count'                => $pending->count(),
            'pending_at_level'     => $pending->count(),
            'needs_review_count'   => count($lowAccuracyExercises),
            'exercises'            => $pending->take(5)->map(fn ($ex) => [
                'id'          => $ex->id,
                'type'        => $ex->type,
                'question'    => $ex->question,
                'difficulty'  => $ex->difficulty,
                'lesson'      => $ex->lesson?->title,
                'points'      => $ex->points,
            ])->toArray(),
            'lesson_suggestion'    => $nextLesson ? [
                'id'       => $nextLesson->id,
                'title'    => $nextLesson->title,
                'level'    => $nextLesson->level,
                'category' => $nextLesson->category,
            ] : null,
        ];
    }

    /**
     * Insight semanal baseado em comparação com a semana anterior.
     */
    public function getWeeklyInsight(User $user): array
    {
        $thisWeek = UserWeeklyXp::where('user_id', $user->id)
            ->where('week_start', now()->startOfWeek()->toDateString())
            ->first();

        $lastWeek = UserWeeklyXp::where('user_id', $user->id)
            ->where('week_start', now()->subWeek()->startOfWeek()->toDateString())
            ->first();

        $thisXp   = (int) ($thisWeek?->xp ?? 0);
        $lastXp   = (int) ($lastWeek?->xp ?? 0);
        $xpDelta  = $thisXp - $lastXp;

        $trend = match (true) {
            $lastXp === 0 && $thisXp === 0 => 'no_activity',
            $lastXp === 0                  => 'first_data',
            $xpDelta > 0                   => 'improving',
            $xpDelta === 0                 => 'stable',
            default                        => 'declining',
        };

        $message = match ($trend) {
            'improving'   => "Você ganhou {$thisXp} XP esta semana — {$xpDelta} a mais que na semana passada! 🚀",
            'stable'      => "Manteve o ritmo: {$thisXp} XP esta semana, igual à semana passada.",
            'declining'   => "Esta semana: {$thisXp} XP. Na semana passada você fez mais ({$lastXp} XP). Que tal retomar? 💪",
            'first_data'  => "Ótimo começo! Você acumulou {$thisXp} XP nesta semana.",
            default       => 'Comece a praticar para gerar seu insight semanal.',
        };

        return [
            'trend'         => $trend,
            'message'       => $message,
            'xp_this_week'  => $thisXp,
            'xp_last_week'  => $lastXp,
            'xp_delta'      => $xpDelta,
            'lessons_this_week'    => (int) ($thisWeek?->lessons_completed ?? 0),
            'exercises_this_week'  => (int) ($thisWeek?->exercises_completed ?? 0),
        ];
    }

    // ─── Internos ─────────────────────────────────────────────────────────────

    /**
     * Normaliza o XP total para um score de 0–100
     * mapeando a escala XP do CEFR (0 – 13.000+).
     */
    private function normalizeXpScore(int $xp): float
    {
        $max = 13_000; // XP mínimo para C2
        return min(100.0, round(($xp / $max) * 100, 2));
    }

    /**
     * Normaliza lições concluídas para um score de 0–100
     * usando 90 lições como referência de C2.
     */
    private function normalizeLessonScore(int $lessonsCompleted): float
    {
        $max = 90;
        return min(100.0, round(($lessonsCompleted / $max) * 100, 2));
    }

    /**
     * Gera dicas de estudo personalizadas com base no nível e nos erros gramaticais.
     *
     * @param  array $grammarFocus  Retorno de getGrammarFocus()
     */
    private function getStudyTips(User $user, array $levelData, array $grammarFocus): array
    {
        $tips     = [];
        $level    = $levelData['code'];
        $accuracy = $levelData['dimensions']['accuracy_score'] ?? 0;
        $topErrors = $grammarFocus['top_errors'] ?? [];

        // Dica 1: baseada na taxa de acerto
        if ($accuracy < 50 && $accuracy > 0) {
            $tips[] = [
                'type'    => 'accuracy',
                'message' => 'Sua taxa de acerto está abaixo de 50%. Revise os exercícios das lições anteriores antes de avançar.',
                'action'  => 'review_exercises',
            ];
        } elseif ($accuracy >= 85) {
            $tips[] = [
                'type'    => 'accuracy',
                'message' => "Excelente precisão ({$accuracy}%)! Você está pronto para avançar para exercícios mais difíceis.",
                'action'  => 'next_level_exercises',
            ];
        }

        // Dica 2: baseada no nível
        $levelTips = [
            'A1' => ['message' => 'Foque em vocabulário básico e frases de apresentação. Use o tutor para conversas simples.', 'action' => 'basic_vocabulary'],
            'A2' => ['message' => 'Pratique situações do cotidiano como shopping, restaurante e transporte.', 'action' => 'daily_situations'],
            'B1' => ['message' => 'Trabalhe verbos irregulares e tempos verbais. Tente conversas sobre trabalho e viagem.', 'action' => 'irregular_verbs'],
            'B2' => ['message' => 'Expanda seu vocabulário com textos e notícias. Pratique expressões idiomáticas.', 'action' => 'advanced_vocabulary'],
        ];

        if (isset($levelTips[$level])) {
            $tips[] = array_merge(['type' => 'level'], $levelTips[$level]);
        }

        // Dica 3: baseada no erro gramatical mais frequente
        if (!empty($topErrors)) {
            $topError = $topErrors[0];
            $tips[] = [
                'type'    => 'grammar',
                'message' => "Erro frequente: {$topError['explanation']}. Pratique isso nas conversações com a Mia.",
                'action'  => 'grammar_practice',
                'error'   => $topError['explanation'],
            ];
        }

        // Dica 4: se o usuário não praticou esta semana
        $weeklyInsight = $this->getWeeklyInsight($user);
        if ($weeklyInsight['xp_this_week'] === 0) {
            $tips[] = [
                'type'    => 'engagement',
                'message' => 'Você ainda não praticou esta semana. Apenas 5 minutos por dia fazem uma enorme diferença!',
                'action'  => 'start_session',
            ];
        }

        return $tips;
    }

    /**
     * Gera uma sugestão de estudo direcionada com base no texto da explicação do erro.
     */
    private function buildStudySuggestion(string $explanation): string
    {
        $lower = strtolower($explanation);

        return match (true) {
            str_contains($lower, 'verb')     => 'Pratique conjugação verbal com os exercícios de Fill in the Blank.',
            str_contains($lower, 'tense')    => 'Revise os tempos verbais na lição de Grammar.',
            str_contains($lower, 'article')  => 'Estude o uso de artigos (a, an, the) nos exercícios de vocabulário.',
            str_contains($lower, 'preposit') => 'Foque em preposições (in, on, at) — faça os exercícios de tradução.',
            str_contains($lower, 'plural')   => 'Revise formação de plural e substantivos compostos.',
            str_contains($lower, 'want')     => 'Use "want to + infinitive" — ex: "I want to eat", não "I want eat".',
            str_contains($lower, 'age')
                || str_contains($lower, 'years') => 'Em inglês, idade usa "to be": "I am 25 years old", não "I have".',
            str_contains($lower, 'since')
                || str_contains($lower, 'for')   => 'Use "for" com duração (for 2 years) e "since" com ponto inicial (since 2020).',
            default => 'Pratique esta estrutura nas conversações diárias com o tutor.',
        };
    }
}
