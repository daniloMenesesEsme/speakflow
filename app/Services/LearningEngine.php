<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\Phrase;
use App\Models\PhraseReviewState;
use App\Models\PronunciationScore;
use App\Models\StudySession;
use App\Models\User;
use App\Models\UserLessonProgress;
use App\ValueObjects\CefrLevel;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * LearningEngine — Motor pedagógico do SpeakFlow.
 *
 * Responsabilidades:
 *  - Calcular progresso detalhado do usuário (CEFR A1–C2)
 *  - Estimar tempo para o próximo nível
 *  - Selecionar a próxima lição ideal
 *  - Selecionar frases para revisão via Spaced Repetition (SM-2)
 *  - Calcular XP por sessão e manter streak
 */
class LearningEngine
{
    // ─── XP por ação ────────────────────────────────────────────────────────
    private const XP_CORRECT_ANSWER  = 10;
    private const XP_LESSON_COMPLETE = 25;
    private const XP_PERFECT_SESSION = 20;
    private const XP_STREAK_BONUS    = 5;   // por dia de streak (cap: 7 dias)

    // ─── Thresholds do SRS (SM-2) ────────────────────────────────────────────
    private const SRS_EASE_DEFAULT   = 2.50;
    private const SRS_EASE_MIN       = 1.30;
    private const SRS_INTERVAL_FIRST = 1;    // dias após 1ª repetição bem-sucedida
    private const SRS_INTERVAL_SECOND= 6;    // dias após 2ª repetição

    // ─── Parâmetros de estimativa de nível ──────────────────────────────────
    private const WORDS_PER_LESSON_AVG          = 10;
    private const ACCURACY_WEIGHT               = 0.40;
    private const PRONUNCIATION_WEIGHT          = 0.30;
    private const STUDY_TIME_WEIGHT             = 0.20;
    private const VOCABULARY_WEIGHT             = 0.10;

    // ══════════════════════════════════════════════════════════════════════════
    // PROGRESSO DO USUÁRIO
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Retorna um snapshot completo do progresso do usuário.
     * Inclui nível CEFR, XP, velocidade de aprendizagem e estatísticas gerais.
     */
    public function calculateUserProgress(User $user): array
    {
        $cefrLevel  = CefrLevel::fromCode($user->level);
        $sessions   = $user->studySessions()->where('completed', true)->get();
        $totalXp    = $user->total_xp;

        $accuracyRate         = $this->calculateAverageAccuracy($sessions);
        $avgPronunciation     = $this->calculateAveragePronunciation($user);
        $wordsLearned         = $this->countLearnedWords($user);
        $lessonsCompleted     = $this->countCompletedLessons($user);
        $learningVelocity     = $this->calculateLearningVelocity($sessions);

        $overallScore = $this->calculateOverallScore(
            $accuracyRate,
            $avgPronunciation,
            $learningVelocity,
            $wordsLearned
        );

        return [
            'level' => [
                'code'                => $cefrLevel->code(),
                'description'         => $cefrLevel->description(),
                'rank'                => $cefrLevel->numericRank(),
                'progress_percentage' => $cefrLevel->progressPercentage($totalXp),
                'xp_to_next_level'    => $cefrLevel->xpToNextLevel($totalXp),
                'next_level'          => $cefrLevel->next()?->code(),
                'is_max_level'        => $cefrLevel->isTop(),
            ],
            'xp' => [
                'total'     => $totalXp,
                'current_level_min' => $cefrLevel->minXp(),
                'current_level_max' => $cefrLevel->maxXp(),
            ],
            'streak' => [
                'days'           => $user->streak_days,
                'last_study_date'=> $user->last_study_date?->toDateString(),
            ],
            'performance' => [
                'accuracy_rate'        => round($accuracyRate, 2),
                'avg_pronunciation'    => round($avgPronunciation, 2),
                'learning_velocity_xp' => round($learningVelocity, 2),
                'overall_score'        => round($overallScore, 2),
            ],
            'totals' => [
                'lessons_completed'  => $lessonsCompleted,
                'words_learned'      => $wordsLearned,
                'study_minutes'      => $sessions->sum('duration_minutes'),
                'sessions_completed' => $sessions->count(),
                'achievements'       => $user->userAchievements()->count(),
            ],
            'daily_goal' => $this->getDailyGoalProgress($user),
            'srs' => [
                'due_for_review' => PhraseReviewState::forUser($user->id)->dueForReview()->count(),
                'total_tracked'  => PhraseReviewState::forUser($user->id)->count(),
            ],
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ESTIMATIVA DE TEMPO PARA O PRÓXIMO NÍVEL
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Estima quantos dias e minutos o usuário precisa para atingir o próximo nível.
     *
     * Considera:
     *   - XP faltante para o próximo nível
     *   - Velocidade média de ganho de XP por sessão
     *   - Taxa de acerto (acelera ou freia a progressão)
     *   - Pontuação média de pronúncia
     *   - Número de palavras aprendidas (vocabulário)
     */
    public function calculateNextLevelEstimate(User $user): array
    {
        $cefrLevel = CefrLevel::fromCode($user->level);

        if ($cefrLevel->isTop()) {
            return [
                'reached_max_level' => true,
                'message'           => 'Parabéns! Você atingiu o nível máximo C2.',
            ];
        }

        $xpRemaining = $cefrLevel->xpToNextLevel($user->total_xp);

        $sessions = $user->studySessions()
            ->where('completed', true)
            ->latest()
            ->limit(20)
            ->get();

        if ($sessions->isEmpty()) {
            return $this->defaultEstimate($xpRemaining, $cefrLevel);
        }

        $avgXpPerSession  = $sessions->avg('xp_earned');
        $avgAccuracy      = $this->calculateAverageAccuracy($sessions);
        $avgPronunciation = $this->calculateAveragePronunciation($user);
        $wordsLearned     = $this->countLearnedWords($user);

        // Fator de performance composto (0.5 a 1.5)
        $performanceFactor = $this->buildPerformanceFactor(
            $avgAccuracy,
            $avgPronunciation,
            $wordsLearned
        );

        // Sessões estimadas necessárias
        $effectiveXpPerSession = max(1, $avgXpPerSession * $performanceFactor);
        $sessionsNeeded        = (int) ceil($xpRemaining / $effectiveXpPerSession);

        // Frequência de estudo (sessões/dia nos últimos 14 dias)
        $studyFrequency = $this->calculateStudyFrequency($user, days: 14);
        $daysNeeded     = $studyFrequency > 0
            ? (int) ceil($sessionsNeeded / $studyFrequency)
            : $sessionsNeeded * 2; // assume estudo em dias alternados

        $avgSessionMinutes = max(1, $sessions->avg('duration_minutes'));
        $minutesNeeded     = (int) round($sessionsNeeded * $avgSessionMinutes);

        return [
            'reached_max_level'        => false,
            'current_level'            => $cefrLevel->code(),
            'next_level'               => $cefrLevel->next()->code(),
            'xp_remaining'             => $xpRemaining,
            'estimated_sessions'       => $sessionsNeeded,
            'estimated_days'           => $daysNeeded,
            'estimated_minutes_total'  => $minutesNeeded,
            'estimated_date'           => Carbon::now()->addDays($daysNeeded)->toDateString(),
            'performance_factor'       => round($performanceFactor, 3),
            'breakdown' => [
                'avg_xp_per_session'   => round($avgXpPerSession, 1),
                'avg_accuracy'         => round($avgAccuracy, 1),
                'avg_pronunciation'    => round($avgPronunciation, 1),
                'study_frequency_day'  => round($studyFrequency, 2),
                'words_learned'        => $wordsLearned,
            ],
            'tips' => $this->buildProgressionTips($avgAccuracy, $avgPronunciation, $studyFrequency),
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SELEÇÃO DA PRÓXIMA LIÇÃO
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Seleciona a próxima lição ideal para o usuário, seguindo a lógica:
     *  1. Se há lição em andamento → continua ela
     *  2. Se tem revisão SRS atrasada → prioriza lição de revisão
     *  3. Caso contrário → próxima lição do nível atual ordenada por `order`
     */
    public function selectNextLesson(User $user): ?array
    {
        // 1. Lição em andamento
        $inProgress = $this->getInProgressLesson($user);
        if ($inProgress) {
            return $this->formatLessonResult($inProgress, 'in_progress');
        }

        // 2. Revisão SRS urgente (mais de 3 frases vencidas)
        $dueSrsCount = PhraseReviewState::forUser($user->id)->dueForReview()->count();
        if ($dueSrsCount >= 3) {
            return [
                'type'     => 'srs_review',
                'lesson'   => null,
                'message'  => "Você tem {$dueSrsCount} frases aguardando revisão.",
                'action'   => 'review',
            ];
        }

        // 3. Próxima lição nova do nível
        $nextLesson = $this->getNextNewLesson($user);
        if ($nextLesson) {
            return $this->formatLessonResult($nextLesson, 'new');
        }

        // 4. Usuário completou tudo no nível — sugere reforço
        return $this->suggestLevelReinforcement($user);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // REPETIÇÃO ESPAÇADA — SELEÇÃO DE FRASES (SM-2)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Retorna as frases que devem ser revisadas agora, baseado no algoritmo SM-2.
     * Ordena por prioridade: mais atrasadas primeiro, depois as novas.
     */
    public function selectPhrasesForReview(User $user, int $limit = 20): Collection
    {
        // Frases com revisão vencida (atrasadas)
        $overdueIds = PhraseReviewState::forUser($user->id)
            ->dueForReview()
            ->orderBy('next_review_at')
            ->limit($limit)
            ->pluck('phrase_id');

        $overduePhrases = Phrase::whereIn('id', $overdueIds)
            ->with(['lesson:id,title,level', 'reviewState' => fn ($q) => $q->where('user_id', $user->id)])
            ->get();

        // Complementa com frases novas se não atingiu o limite
        $remaining = $limit - $overduePhrases->count();
        $newPhrases = collect();

        if ($remaining > 0) {
            $trackedIds = PhraseReviewState::forUser($user->id)->pluck('phrase_id');

            $newPhrases = Phrase::whereNotIn('id', $trackedIds)
                ->whereHas('lesson', fn ($q) => $q
                    ->active()
                    ->byLevel($user->level)
                    ->whereHas('language', fn ($lq) => $lq->where('code', $user->target_language))
                )
                ->with('lesson:id,title,level')
                ->inRandomOrder()
                ->limit($remaining)
                ->get();
        }

        $all = $overduePhrases->merge($newPhrases);

        return $all->map(fn (Phrase $phrase) => [
            'id'              => $phrase->id,
            'english_text'    => $phrase->english_text,
            'portuguese_text' => $phrase->portuguese_text,
            'audio_path'      => $phrase->audio_path,
            'phonetic'        => $phrase->phonetic,
            'difficulty'      => $phrase->difficulty,
            'lesson'          => $phrase->lesson ? [
                'id'    => $phrase->lesson->id,
                'title' => $phrase->lesson->title,
                'level' => $phrase->lesson->level,
            ] : null,
            'srs_state' => $this->formatSrsState($phrase->reviewState->first()),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ALGORITMO SM-2 — ATUALIZAÇÃO DO ESTADO DE REVISÃO
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Processa a avaliação de uma frase pelo usuário e atualiza o estado SM-2.
     *
     * @param  int  $quality  Nota de 0 a 5:
     *                        5 = resposta correta e imediata
     *                        4 = correta com hesitação leve
     *                        3 = correta com dificuldade
     *                        2 = incorreta, mas resposta foi fácil de lembrar
     *                        1 = incorreta, resposta difícil
     *                        0 = falha total
     */
    public function processReview(User $user, Phrase $phrase, int $quality): PhraseReviewState
    {
        $quality = max(0, min(5, $quality));

        $state = PhraseReviewState::firstOrCreate(
            ['user_id' => $user->id, 'phrase_id' => $phrase->id],
            [
                'repetitions'      => 0,
                'ease_factor'      => self::SRS_EASE_DEFAULT,
                'interval_days'    => self::SRS_INTERVAL_FIRST,
                'total_reviews'    => 0,
                'cumulative_score' => 0,
            ]
        );

        // Atualiza métricas acumuladas
        $state->total_reviews    += 1;
        $state->cumulative_score += $quality;
        $state->last_quality      = $quality;
        $state->last_reviewed_at  = now();

        if ($quality < 3) {
            // Falha: reinicia a sequência de repetições
            $state->repetitions  = 0;
            $state->interval_days = self::SRS_INTERVAL_FIRST;
        } else {
            // Sucesso: avança no intervalo
            $state->interval_days = $this->computeNextInterval(
                $state->repetitions,
                $state->interval_days,
                (float) $state->ease_factor
            );
            $state->repetitions += 1;
        }

        // Atualiza o fator de facilidade (SM-2 original)
        $newEf = (float) $state->ease_factor
            + (0.1 - (5 - $quality) * (0.08 + (5 - $quality) * 0.02));

        $state->ease_factor   = max(self::SRS_EASE_MIN, round($newEf, 2));
        $state->next_review_at = Carbon::now()->addDays($state->interval_days);

        $state->save();

        return $state;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // XP E PROGRESSÃO
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Calcula o XP ganho em uma sessão de exercícios.
     */
    public function calculateXpForSession(
        int $correctAnswers,
        int $totalAnswers,
        bool $lessonCompleted,
        int $streakDays
    ): int {
        if ($totalAnswers === 0) {
            return 0;
        }

        $accuracy        = $correctAnswers / $totalAnswers;
        $baseXp          = $correctAnswers * self::XP_CORRECT_ANSWER;
        $streakBonus     = min($streakDays, 7) * self::XP_STREAK_BONUS;
        $completionBonus = $lessonCompleted ? self::XP_LESSON_COMPLETE : 0;
        $perfectBonus    = $accuracy >= 1.0 ? self::XP_PERFECT_SESSION : 0;

        // Penalidade suave para sessões com menos de 50% de acerto
        $accuracyMultiplier = max(0.5, $accuracy);

        return (int) round(
            ($baseXp + $streakBonus + $completionBonus + $perfectBonus) * $accuracyMultiplier
        );
    }

    /**
     * Aplica o XP ganho ao usuário, atualiza nível e streak.
     * Retorna diff com o estado anterior para feedback no app.
     */
    public function updateUserProgress(User $user, StudySession $session): array
    {
        $previousLevel = $user->level;
        $previousXp    = $user->total_xp;

        $user->total_xp += $session->xp_earned;

        $this->updateStreak($user);

        $newCefr   = CefrLevel::fromXp($user->total_xp);
        $leveledUp = $newCefr->code() !== $previousLevel;

        if ($leveledUp) {
            $user->level = $newCefr->code();
        }

        $user->save();

        return [
            'xp_earned'         => $session->xp_earned,
            'total_xp'          => $user->total_xp,
            'xp_before'         => $previousXp,
            'leveled_up'        => $leveledUp,
            'previous_level'    => $previousLevel,
            'new_level'         => $newCefr->code(),
            'level_description' => $newCefr->description(),
            'streak_days'       => $user->streak_days,
            'xp_to_next_level'  => $newCefr->xpToNextLevel($user->total_xp),
            'level_progress_pct'=> $newCefr->progressPercentage($user->total_xp),
        ];
    }

    /**
     * Atualiza o progresso do usuário em uma lição específica.
     */
    public function updateLessonProgress(
        User $user,
        Lesson $lesson,
        int $correctAnswers,
        int $totalAnswers
    ): UserLessonProgress {
        $percentage = $totalAnswers > 0
            ? (int) round(($correctAnswers / $totalAnswers) * 100)
            : 0;

        $progress = UserLessonProgress::firstOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $lesson->id],
            ['completion_percentage' => 0, 'times_completed' => 0, 'best_score' => 0]
        );

        if ($percentage > $progress->best_score) {
            $progress->best_score = $percentage;
        }

        if ($percentage >= 80) {
            $progress->completion_percentage = 100;
            $progress->times_completed      += 1;
        } else {
            $progress->completion_percentage = max($progress->completion_percentage, $percentage);
        }

        $progress->last_accessed_at = now();
        $progress->save();

        return $progress;
    }

    /**
     * Atualiza streak de dias consecutivos.
     */
    public function updateStreak(User $user): void
    {
        $lastStudy = $user->last_study_date;

        if ($lastStudy === null) {
            $user->streak_days = 1;
        } elseif ($lastStudy->isYesterday()) {
            $user->streak_days += 1;
        } elseif (!$lastStudy->isToday()) {
            $user->streak_days = 1;
        }

        $user->last_study_date = Carbon::today();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // METAS E ESTATÍSTICAS
    // ══════════════════════════════════════════════════════════════════════════

    public function getDailyGoalProgress(User $user): array
    {
        $todayMinutes = StudySession::where('user_id', $user->id)
            ->whereDate('created_at', Carbon::today())
            ->sum('duration_minutes');

        $percentage = $user->daily_goal_minutes > 0
            ? min(100, (int) round(($todayMinutes / $user->daily_goal_minutes) * 100))
            : 0;

        return [
            'minutes_studied' => $todayMinutes,
            'goal_minutes'    => $user->daily_goal_minutes,
            'percentage'      => $percentage,
            'goal_reached'    => $percentage >= 100,
        ];
    }

    public function getUserStatistics(User $user): array
    {
        $sessions = $user->studySessions()->where('completed', true)->get();

        return [
            'total_sessions'      => $sessions->count(),
            'total_minutes'       => $sessions->sum('duration_minutes'),
            'total_xp'            => $user->total_xp,
            'lessons_completed'   => $this->countCompletedLessons($user),
            'words_learned'       => $this->countLearnedWords($user),
            'current_level'       => $user->level,
            'streak_days'         => $user->streak_days,
            'achievements_count'  => $user->userAchievements()->count(),
            'avg_accuracy'        => round($this->calculateAverageAccuracy($sessions), 2),
            'avg_pronunciation'   => round($this->calculateAveragePronunciation($user), 2),
            'next_level_estimate' => $this->calculateNextLevelEstimate($user),
        ];
    }

    public function getNextLevelXp(string $levelCode): ?int
    {
        return CefrLevel::fromCode($levelCode)->maxXp();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PRIVADOS — SM-2
    // ══════════════════════════════════════════════════════════════════════════

    private function computeNextInterval(
        int $repetitions,
        int $currentInterval,
        float $easeFactor
    ): int {
        return match (true) {
            $repetitions === 0 => self::SRS_INTERVAL_FIRST,
            $repetitions === 1 => self::SRS_INTERVAL_SECOND,
            default            => (int) round($currentInterval * $easeFactor),
        };
    }

    private function formatSrsState(?PhraseReviewState $state): array
    {
        if ($state === null) {
            return [
                'status'         => 'new',
                'mastery_level'  => 'new',
                'repetitions'    => 0,
                'next_review_at' => null,
                'is_due'         => true,
            ];
        }

        return [
            'status'          => $state->isDueForReview() ? 'due' : 'scheduled',
            'mastery_level'   => $state->mastery_level,
            'repetitions'     => $state->repetitions,
            'ease_factor'     => (float) $state->ease_factor,
            'interval_days'   => $state->interval_days,
            'next_review_at'  => $state->next_review_at?->toISOString(),
            'days_until_review'=> $state->days_until_review,
            'is_due'          => $state->isDueForReview(),
            'last_quality'    => $state->last_quality,
            'average_quality' => $state->average_quality,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PRIVADOS — SELEÇÃO DE LIÇÃO
    // ══════════════════════════════════════════════════════════════════════════

    private function getInProgressLesson(User $user): ?Lesson
    {
        $inProgressId = UserLessonProgress::where('user_id', $user->id)
            ->whereBetween('completion_percentage', [1, 99])
            ->orderByDesc('last_accessed_at')
            ->value('lesson_id');

        if (!$inProgressId) {
            return null;
        }

        return Lesson::where('id', $inProgressId)->active()->first();
    }

    private function getNextNewLesson(User $user): ?Lesson
    {
        $completedIds = UserLessonProgress::where('user_id', $user->id)
            ->where('completion_percentage', 100)
            ->pluck('lesson_id');

        return Lesson::whereHas('language', fn ($q) => $q->where('code', $user->target_language))
            ->byLevel($user->level)
            ->whereNotIn('id', $completedIds)
            ->active()
            ->ordered()
            ->first();
    }

    private function suggestLevelReinforcement(User $user): array
    {
        $reinforcementLesson = Lesson::whereHas('language', fn ($q) => $q->where('code', $user->target_language))
            ->byLevel($user->level)
            ->active()
            ->inRandomOrder()
            ->first();

        return [
            'type'    => 'reinforcement',
            'lesson'  => $reinforcementLesson ? $this->formatLessonData($reinforcementLesson) : null,
            'message' => 'Você completou todas as lições deste nível! Pratique para consolidar.',
            'action'  => 'review',
        ];
    }

    private function formatLessonResult(Lesson $lesson, string $type): array
    {
        return [
            'type'   => $type,
            'lesson' => $this->formatLessonData($lesson),
            'action' => 'start',
        ];
    }

    private function formatLessonData(Lesson $lesson): array
    {
        return [
            'id'          => $lesson->id,
            'title'       => $lesson->title,
            'level'       => $lesson->level,
            'category'    => $lesson->category,
            'xp_reward'   => $lesson->xp_reward,
            'description' => $lesson->description,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PRIVADOS — MÉTRICAS DE PERFORMANCE
    // ══════════════════════════════════════════════════════════════════════════

    private function calculateAverageAccuracy(Collection $sessions): float
    {
        $relevant = $sessions->where('exercises_completed', '>', 0);

        if ($relevant->isEmpty()) {
            return 0.0;
        }

        return (float) $relevant->avg('accuracy_percentage');
    }

    private function calculateAveragePronunciation(User $user): float
    {
        $avg = PronunciationScore::where('user_id', $user->id)->avg('score');

        return (float) ($avg ?? 0);
    }

    /**
     * Velocidade de aprendizagem: XP médio ganho por hora de estudo.
     */
    private function calculateLearningVelocity(Collection $sessions): float
    {
        $relevant = $sessions->where('duration_minutes', '>', 0);

        if ($relevant->isEmpty()) {
            return 0.0;
        }

        $totalXp      = $relevant->sum('xp_earned');
        $totalMinutes = $relevant->sum('duration_minutes');

        return $totalMinutes > 0
            ? round(($totalXp / $totalMinutes) * 60, 2)
            : 0.0;
    }

    private function countCompletedLessons(User $user): int
    {
        return UserLessonProgress::where('user_id', $user->id)
            ->where('completion_percentage', 100)
            ->count();
    }

    private function countLearnedWords(User $user): int
    {
        // Aproximação: frases em lições concluídas × palavras médias por frase
        $completedLessonIds = UserLessonProgress::where('user_id', $user->id)
            ->where('completion_percentage', 100)
            ->pluck('lesson_id');

        return Phrase::whereIn('lesson_id', $completedLessonIds)->count()
            * self::WORDS_PER_LESSON_AVG;
    }

    /**
     * Sessões por dia (média nos últimos N dias com ao menos 1 sessão).
     */
    private function calculateStudyFrequency(User $user, int $days = 14): float
    {
        $studyDays = StudySession::where('user_id', $user->id)
            ->where('created_at', '>=', Carbon::now()->subDays($days))
            ->where('completed', true)
            ->selectRaw('DATE(created_at) as study_date')
            ->distinct()
            ->count();

        return $studyDays > 0 ? round($studyDays / $days, 3) : 0.0;
    }

    /**
     * Score geral de performance entre 0 e 100, ponderado pelos pesos definidos.
     */
    private function calculateOverallScore(
        float $accuracy,
        float $pronunciation,
        float $velocityXpHour,
        int $wordsLearned
    ): float {
        $velocityNorm = min(100, ($velocityXpHour / 300) * 100);  // 300 XP/hora = 100%
        $vocabNorm    = min(100, ($wordsLearned / 1000) * 100);   // 1000 palavras = 100%

        return (
            $accuracy      * self::ACCURACY_WEIGHT      +
            $pronunciation * self::PRONUNCIATION_WEIGHT +
            $velocityNorm  * self::STUDY_TIME_WEIGHT    +
            $vocabNorm     * self::VOCABULARY_WEIGHT
        );
    }

    /**
     * Fator de performance para ajustar a estimativa de nível (0.5 a 1.5).
     */
    private function buildPerformanceFactor(
        float $accuracy,
        float $pronunciation,
        int $wordsLearned
    ): float {
        $normalized = (
            ($accuracy / 100)      * self::ACCURACY_WEIGHT      +
            ($pronunciation / 100) * self::PRONUNCIATION_WEIGHT +
            (min($wordsLearned, 500) / 500) * (self::VOCABULARY_WEIGHT + self::STUDY_TIME_WEIGHT)
        );

        // Escala de 0.5 (péssimo) a 1.5 (excelente)
        return round(0.5 + $normalized, 3);
    }

    private function buildProgressionTips(
        float $accuracy,
        float $pronunciation,
        float $studyFrequency
    ): array {
        $tips = [];

        if ($accuracy < 70) {
            $tips[] = 'Foque em exercícios mais fáceis antes de avançar para consolidar o vocabulário.';
        }

        if ($pronunciation < 70) {
            $tips[] = 'Pratique pronúncia diariamente — isso acelera sua progressão em 30%.';
        }

        if ($studyFrequency < 0.5) {
            $tips[] = 'Estude com mais frequência. Sessões curtas diárias superam longas sessões semanais.';
        }

        if ($accuracy >= 90 && $pronunciation >= 85) {
            $tips[] = 'Ótimo desempenho! Continue nesse ritmo para atingir o próximo nível rapidamente.';
        }

        return $tips;
    }

    private function defaultEstimate(int $xpRemaining, CefrLevel $level): array
    {
        $estimatedSessions = (int) ceil($xpRemaining / 50); // assume 50 XP/sessão inicial

        return [
            'reached_max_level'       => false,
            'current_level'           => $level->code(),
            'next_level'              => $level->next()->code(),
            'xp_remaining'            => $xpRemaining,
            'estimated_sessions'      => $estimatedSessions,
            'estimated_days'          => $estimatedSessions * 2,
            'estimated_minutes_total' => $estimatedSessions * 15,
            'estimated_date'          => Carbon::now()->addDays($estimatedSessions * 2)->toDateString(),
            'performance_factor'      => 1.0,
            'message'                 => 'Estimativa baseada em padrões gerais. Estude mais para personalizar.',
        ];
    }
}
