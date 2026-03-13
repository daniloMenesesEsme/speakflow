<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserWeeklyXp;
use Illuminate\Support\Collection;

class LeaderboardService
{
    private const TOP_LIMIT = 50;

    /**
     * Registra XP semanal ao usuário.
     * Deve ser chamado sempre que o usuário ganhar XP (exercício ou lição).
     *
     * @param  User  $user
     * @param  int   $xpDelta           XP ganho nesta ação
     * @param  bool  $lessonCompleted   Se uma lição foi concluída
     * @param  bool  $exerciseCorrect   Se um exercício foi respondido corretamente
     */
    public function record(
        User $user,
        int  $xpDelta         = 0,
        bool $lessonCompleted = false,
        bool $exerciseCorrect = false,
    ): void {
        if ($xpDelta <= 0 && ! $lessonCompleted && ! $exerciseCorrect) {
            return;
        }

        $weekStart = UserWeeklyXp::currentWeekStart();

        $record = UserWeeklyXp::firstOrCreate(
            ['user_id' => $user->id, 'week_start' => $weekStart],
            ['xp' => 0, 'lessons_completed' => 0, 'exercises_completed' => 0]
        );

        if ($xpDelta > 0) {
            $record->increment('xp', $xpDelta);
        }
        if ($lessonCompleted) {
            $record->increment('lessons_completed');
        }
        if ($exerciseCorrect) {
            $record->increment('exercises_completed');
        }
    }

    /**
     * Retorna o ranking global da semana atual (top 50).
     * Inclui nome do usuário e avatar para exibição no app.
     */
    public function getWeeklyLeaderboard(): Collection
    {
        $weekStart = UserWeeklyXp::currentWeekStart();

        return UserWeeklyXp::with('user:id,name,avatar,level')
            ->where('week_start', $weekStart)
            ->where('xp', '>', 0)
            ->orderByDesc('xp')
            ->orderBy('updated_at')      // desempate: quem atingiu o XP primeiro
            ->limit(self::TOP_LIMIT)
            ->get()
            ->map(function ($entry, int $index) {
                return [
                    'rank'               => $index + 1,
                    'user'               => $entry->user->name,
                    'avatar'             => $entry->user->avatar,
                    'level'              => $entry->user->level,
                    'xp'                 => $entry->xp,
                    'lessons_completed'  => $entry->lessons_completed,
                    'exercises_completed'=> $entry->exercises_completed,
                ];
            });
    }

    /**
     * Retorna a posição do usuário autenticado no ranking desta semana.
     * Se não estiver no top 50, ainda retorna a posição real calculada.
     */
    public function getUserRank(User $user): array
    {
        $weekStart = UserWeeklyXp::currentWeekStart();

        $myRecord = UserWeeklyXp::where('user_id', $user->id)
            ->where('week_start', $weekStart)
            ->first();

        if (! $myRecord || $myRecord->xp === 0) {
            return [
                'rank'                => null,
                'xp'                  => 0,
                'user'                => $user->name,
                'level'               => $user->level,
                'lessons_completed'   => 0,
                'exercises_completed' => 0,
                'message'             => 'Você ainda não praticou esta semana.',
            ];
        }

        // Conta quantos usuários têm mais XP (posição real, não limitada ao top 50)
        $rank = UserWeeklyXp::where('week_start', $weekStart)
            ->where('xp', '>', $myRecord->xp)
            ->count() + 1;

        return [
            'rank'                => $rank,
            'xp'                  => $myRecord->xp,
            'user'                => $user->name,
            'level'               => $user->level,
            'lessons_completed'   => $myRecord->lessons_completed,
            'exercises_completed' => $myRecord->exercises_completed,
        ];
    }
}
