<?php

namespace App\Services;

use App\Models\DailyMission;
use App\Models\User;
use App\Models\UserDailyMission;
use Illuminate\Support\Facades\DB;

class DailyMissionService
{
    // ─── API pública ─────────────────────────────────────────────────────────

    /**
     * Retorna as missões do dia com o progresso atual do usuário.
     * Cria automaticamente os registros user_daily_missions se ainda não existirem.
     */
    public function getTodayMissions(User $user): array
    {
        $today = now()->toDateString();

        // Carrega missões ativas e garante que existam registros para hoje
        $missions = DailyMission::active()->orderBy('type')->get();

        foreach ($missions as $mission) {
            UserDailyMission::firstOrCreate(
                [
                    'user_id'    => $user->id,
                    'mission_id' => $mission->id,
                    'date'       => $today,
                ],
                [
                    'progress'  => 0,
                    'completed' => false,
                ]
            );
        }

        // Busca os registros do dia com a missão carregada
        $userMissions = UserDailyMission::where('user_id', $user->id)
            ->where('date', $today)
            ->with('mission')
            ->get();

        return $userMissions->map(fn ($um) => $this->formatMission($um))->toArray();
    }

    /**
     * Incrementa o progresso da missão do tipo informado.
     * Chama checkCompletion() internamente quando o progresso atinge o target.
     * Retorna as missões afetadas (para incluir na resposta da API).
     *
     * @return array Missões atualizadas
     */
    public function updateProgress(User $user, string $type): array
    {
        if (!in_array($type, DailyMission::TYPES)) {
            return [];
        }

        $today    = now()->toDateString();
        $affected = [];

        // Missões ativas do tipo informado
        $missions = DailyMission::active()->ofType($type)->get();

        foreach ($missions as $mission) {
            $userMission = UserDailyMission::firstOrCreate(
                [
                    'user_id'    => $user->id,
                    'mission_id' => $mission->id,
                    'date'       => $today,
                ],
                ['progress' => 0, 'completed' => false]
            );

            // Não atualiza se já concluída
            if ($userMission->completed) {
                $affected[] = $this->formatMission($userMission->load('mission'));
                continue;
            }

            // Incrementa progress
            $userMission->increment('progress');
            $userMission->refresh();

            // Verificar conclusão
            if ($userMission->progress >= $mission->target) {
                $this->completeMission($user, $userMission, $mission);
            }

            $affected[] = $this->formatMission($userMission->load('mission'));
        }

        return $affected;
    }

    /**
     * Verifica quais missões do dia foram concluídas pelo usuário.
     * Útil para exibir notificações ou badges no app.
     */
    public function checkCompletion(User $user): array
    {
        $today = now()->toDateString();

        $completed = UserDailyMission::where('user_id', $user->id)
            ->where('date', $today)
            ->where('completed', true)
            ->with('mission')
            ->get();

        $pending = UserDailyMission::where('user_id', $user->id)
            ->where('date', $today)
            ->where('completed', false)
            ->with('mission')
            ->get();

        return [
            'completed_count' => $completed->count(),
            'pending_count'   => $pending->count(),
            'total'           => $completed->count() + $pending->count(),
            'all_done'        => $pending->isEmpty() && $completed->isNotEmpty(),
            'completed'       => $completed->map(fn ($um) => $this->formatMission($um))->toArray(),
            'pending'         => $pending->map(fn ($um) => $this->formatMission($um))->toArray(),
        ];
    }

    /**
     * Retorna o progresso resumido do dia (para o endpoint /missions/progress).
     */
    public function getDailyProgress(User $user): array
    {
        $today = now()->toDateString();

        // Garante que os registros existam
        $this->getTodayMissions($user);

        $userMissions = UserDailyMission::where('user_id', $user->id)
            ->where('date', $today)
            ->with('mission')
            ->get();

        $completedCount = $userMissions->where('completed', true)->count();
        $totalCount     = $userMissions->count();
        $totalXpEarned  = $userMissions
            ->where('completed', true)
            ->sum(fn ($um) => $um->mission->xp_reward ?? 0);

        $totalXpAvailable = $userMissions->sum(fn ($um) => $um->mission->xp_reward ?? 0);

        return [
            'date'              => $today,
            'completed'         => $completedCount,
            'total'             => $totalCount,
            'all_done'          => $completedCount === $totalCount && $totalCount > 0,
            'xp_earned_today'   => $totalXpEarned,
            'xp_available'      => $totalXpAvailable,
            'completion_rate'   => $totalCount > 0
                ? round(($completedCount / $totalCount) * 100)
                : 0,
            'missions'          => $userMissions->map(fn ($um) => $this->formatMission($um))->toArray(),
        ];
    }

    // ─── Internos ─────────────────────────────────────────────────────────────

    /**
     * Marca a missão como concluída e adiciona o XP ao usuário.
     */
    private function completeMission(User $user, UserDailyMission $userMission, DailyMission $mission): void
    {
        DB::transaction(function () use ($user, $userMission, $mission) {
            $userMission->update([
                'completed'    => true,
                'completed_at' => now(),
            ]);

            // Concede XP ao usuário
            $user->increment('total_xp', $mission->xp_reward);
        });
    }

    /**
     * Formata um UserDailyMission para exibição na API.
     */
    private function formatMission(UserDailyMission $um): array
    {
        return [
            'id'           => $um->mission?->id,
            'type'         => $um->mission?->type,
            'title'        => $um->mission?->title,
            'description'  => $um->mission?->description,
            'icon'         => $um->mission?->icon,
            'progress'     => $um->progress,
            'target'       => $um->mission?->target ?? 0,
            'xp_reward'    => $um->mission?->xp_reward ?? 0,
            'completed'    => $um->completed,
            'completed_at' => $um->completed_at?->toISOString(),
            'percent'      => $um->percentComplete(),
        ];
    }
}
