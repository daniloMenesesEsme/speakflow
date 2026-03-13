<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\User;
use App\Models\UserAchievement;

class AchievementService
{
    /**
     * Verifica todas as conquistas não obtidas e concede as que o usuário
     * já cumpriu as condições. Retorna o array de conquistas recém-desbloqueadas.
     */
    public function checkAndAwardAchievements(User $user): array
    {
        $newAchievements = [];

        $earnedIds = $user->userAchievements()->pluck('achievement_id')->toArray();

        $pending = Achievement::active()
            ->whereNotIn('id', $earnedIds)
            ->get();

        foreach ($pending as $achievement) {
            if ($this->meetsCondition($user, $achievement)) {
                $this->awardAchievement($user, $achievement);
                $newAchievements[] = $achievement;
            }
        }

        // Bônus de XP pelas conquistas desbloqueadas nesta verificação
        if (! empty($newAchievements)) {
            $bonusXp = collect($newAchievements)->sum('xp_reward');
            $user->increment('total_xp', $bonusXp);
        }

        return $newAchievements;
    }

    /**
     * Concede uma conquista específica ao usuário (idempotente via firstOrCreate).
     */
    public function awardAchievement(User $user, Achievement $achievement): UserAchievement
    {
        return UserAchievement::firstOrCreate(
            ['user_id' => $user->id, 'achievement_id' => $achievement->id],
            ['earned_at' => now()]
        );
    }

    // ─── Verificação de condições ────────────────────────────────────────────

    private function meetsCondition(User $user, Achievement $achievement): bool
    {
        return match ($achievement->condition_type) {
            'first_lesson'        => $this->countCompletedLessons($user) >= 1,
            'lessons_completed'   => $this->countCompletedLessons($user) >= $achievement->condition_value,
            'exercises_completed' => $this->countCorrectExercises($user) >= $achievement->condition_value,
            'study_streak'        => $user->streak_days >= $achievement->condition_value,
            'total_xp'            => $user->total_xp >= $achievement->condition_value,
            'pronunciation_score' => $this->hasHighPronunciationScore($user, $achievement->condition_value),
            'daily_goal'          => $this->hasReachedDailyGoalDays($user, $achievement->condition_value),
            default               => false,
        };
    }

    /**
     * Conta lições formalmente concluídas (flag `completed = true`).
     * Usa o campo correto após a migration de conclusão.
     */
    private function countCompletedLessons(User $user): int
    {
        return $user->lessonProgress()
            ->where('completed', true)
            ->count();
    }

    /**
     * Conta total de exercícios respondidos corretamente pelo menos uma vez.
     * Considera apenas tentativas com pontos concedidos (primeira vez correta).
     */
    private function countCorrectExercises(User $user): int
    {
        return $user->exerciseAttempts()
            ->where('correct', true)
            ->where('points_earned', '>', 0)
            ->count();
    }

    private function hasHighPronunciationScore(User $user, int $threshold): bool
    {
        return $user->pronunciationScores()
            ->where('score', '>=', $threshold)
            ->exists();
    }

    private function hasReachedDailyGoalDays(User $user, int $days): bool
    {
        // PostgreSQL não aceita alias no HAVING; usa a expressão diretamente
        return \App\Models\StudySession::where('user_id', $user->id)
            ->selectRaw('DATE(created_at) as study_date')
            ->groupBy('study_date')
            ->havingRaw('SUM(duration_minutes) >= ?', [$user->daily_goal_minutes])
            ->count() >= $days;
    }
}
