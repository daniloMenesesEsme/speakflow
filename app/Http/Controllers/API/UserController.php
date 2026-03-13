<?php

namespace App\Http\Controllers\API;

use App\Services\AchievementService;
use App\Services\DailyActivityService;
use Illuminate\Http\JsonResponse;

class UserController extends BaseController
{
    public function __construct(
        private DailyActivityService $dailyActivity,
        private AchievementService   $achievementService,
    ) {
    }

    /**
     * GET /api/v1/users/stats
     *
     * Retorna estatísticas consolidadas do usuário autenticado.
     */
    public function stats(): JsonResponse
    {
        $user  = auth()->user();
        $stats = $this->dailyActivity->getUserStats($user);

        return $this->success($stats, 'Estatísticas do usuário.');
    }

    /**
     * GET /api/v1/users/achievements
     *
     * Lista as conquistas desbloqueadas pelo usuário autenticado,
     * mais metadados das conquistas não desbloqueadas (mostra progresso).
     */
    public function achievements(): JsonResponse
    {
        $user = auth()->user();

        // Conquistas já desbloqueadas
        $earned = $user->userAchievements()
            ->with('achievement')
            ->orderByDesc('earned_at')
            ->get()
            ->map(fn ($ua) => [
                'id'          => $ua->achievement->id,
                'title'       => $ua->achievement->title,
                'description' => $ua->achievement->description,
                'icon'        => $ua->achievement->icon,
                'xp_reward'   => $ua->achievement->xp_reward,
                'category'    => $ua->achievement->category,
                'unlocked'    => true,
                'unlocked_at' => $ua->earned_at->toISOString(),
            ]);

        // Conquistas ainda não desbloqueadas
        $earnedIds = $earned->pluck('id')->toArray();
        $locked    = \App\Models\Achievement::active()
            ->whereNotIn('id', $earnedIds)
            ->get()
            ->map(fn ($a) => [
                'id'          => $a->id,
                'title'       => $a->title,
                'description' => $a->description,
                'icon'        => $a->icon,
                'xp_reward'   => $a->xp_reward,
                'category'    => $a->category,
                'unlocked'    => false,
                'unlocked_at' => null,
            ]);

        // collect() garante Support\Collection simples (não Eloquent Collection)
        // antes do merge, evitando getKey() em arrays mapeados
        return $this->success([
            'achievements' => collect($earned->toArray())->concat($locked->toArray())->values(),
            'stats'        => [
                'total'                      => $earned->count() + $locked->count(),
                'earned'                     => $earned->count(),
                'total_xp_from_achievements' => $earned->sum('xp_reward'),
            ],
        ], 'Conquistas do usuário.');
    }
}
