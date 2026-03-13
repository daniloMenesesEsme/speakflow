<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserDailyActivity;
use Illuminate\Support\Facades\DB;

class DailyActivityService
{
    public function __construct(private AchievementService $achievementService)
    {
    }

    /**
     * Registra atividade do usuário no dia atual e atualiza o streak.
     *
     * Deve ser chamado sempre que o usuário realizar uma ação de aprendizado:
     * - Responder um exercício corretamente
     * - Concluir uma lição
     *
     * @param  User  $user
     * @param  int   $xpDelta          XP ganho nesta ação
     * @param  bool  $lessonCompleted  Se uma lição foi concluída nesta ação
     * @param  bool  $exerciseCorrect  Se um exercício foi respondido corretamente
     * @return UserDailyActivity       Registro do dia atualizado
     */
    public function record(
        User $user,
        int  $xpDelta         = 0,
        bool $lessonCompleted = false,
        bool $exerciseCorrect = false,
    ): UserDailyActivity {
        $today = now()->toDateString();

        return DB::transaction(function () use ($user, $today, $xpDelta, $lessonCompleted, $exerciseCorrect) {
            $todayActivity = UserDailyActivity::where('user_id', $user->id)
                ->where('date', $today)
                ->first();

            $isFirstActivityToday = $todayActivity === null;

            // Cria ou atualiza o registro do dia
            $todayActivity = UserDailyActivity::updateOrCreate(
                ['user_id' => $user->id, 'date' => $today],
                []   // campos incrementais abaixo
            );

            if ($xpDelta > 0) {
                $todayActivity->increment('xp_earned', $xpDelta);
            }
            if ($lessonCompleted) {
                $todayActivity->increment('lessons_completed');
            }
            if ($exerciseCorrect) {
                $todayActivity->increment('exercises_answered');
            }

            // Atualiza streak apenas na primeira atividade do dia
            if ($isFirstActivityToday) {
                $this->updateStreak($user, $today);
            }

            return $todayActivity->fresh();
        });
    }

    /**
     * Recalcula o streak do usuário com base no histórico de atividades.
     *
     * Regras:
     * - Praticou ontem E hoje → streak + 1
     * - Pulou um ou mais dias → streak reinicia em 1
     * - Já tinha praticado hoje (mesmo dia, 2ª chamada) → sem alteração
     */
    public function updateStreak(User $user, string $today): void
    {
        $yesterday = now()->subDay()->toDateString();

        $practicedYesterday = UserDailyActivity::where('user_id', $user->id)
            ->where('date', $yesterday)
            ->exists();

        if ($practicedYesterday) {
            $user->increment('streak_days');
        } else {
            $user->update(['streak_days' => 1]);
        }

        // Verifica conquistas de streak após atualizar o campo
        $this->achievementService->checkAndAwardAchievements($user->fresh());
    }

    /**
     * Retorna o streak atual recalculado direto do histórico.
     * Útil para validar a consistência do campo users.streak_days.
     */
    public function calculateCurrentStreak(User $user): int
    {
        $streak = 0;
        $checkDate = now()->toDateString();

        // Verifica se praticou hoje; se não, começa de ontem
        $hasToday = UserDailyActivity::where('user_id', $user->id)
            ->where('date', $checkDate)
            ->exists();

        if (! $hasToday) {
            $checkDate = now()->subDay()->toDateString();
        }

        // Percorre os dias consecutivos para trás
        while (true) {
            $exists = UserDailyActivity::where('user_id', $user->id)
                ->where('date', $checkDate)
                ->exists();

            if (! $exists) {
                break;
            }

            $streak++;
            $checkDate = now()->parse($checkDate)->subDay()->toDateString();
        }

        return $streak;
    }

    /**
     * Agrega as estatísticas gerais do usuário.
     */
    public function getUserStats(User $user): array
    {
        $lessonsCompleted = \App\Models\UserLessonProgress::where('user_id', $user->id)
            ->where('completed', true)
            ->count();

        $exercisesCompleted = \App\Models\UserExerciseAttempt::where('user_id', $user->id)
            ->where('correct', true)
            ->count();

        $totalMinutesStudied = \App\Models\StudySession::where('user_id', $user->id)
            ->where('completed', true)
            ->sum('duration_minutes');

        $currentStreak = $this->calculateCurrentStreak($user);

        // Mantém o campo do banco sincronizado se houver divergência
        if ($user->streak_days !== $currentStreak) {
            $user->update(['streak_days' => $currentStreak]);
        }

        return [
            'total_xp'            => (int) $user->total_xp,
            'streak_days'         => $currentStreak,
            'lessons_completed'   => $lessonsCompleted,
            'exercises_completed' => $exercisesCompleted,
            'total_minutes'       => (int) $totalMinutesStudied,
            'level'               => $user->level,
            'daily_goal_minutes'  => $user->daily_goal_minutes,
        ];
    }
}
