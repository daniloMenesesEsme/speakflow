<?php

namespace App\Http\Controllers\API;

use App\Models\UserWeeklyXp;
use App\Services\LeaderboardService;
use Illuminate\Http\JsonResponse;

class LeaderboardController extends BaseController
{
    public function __construct(private LeaderboardService $leaderboard)
    {
    }

    /**
     * GET /api/v1/leaderboard
     *
     * Top 50 usuários com mais XP na semana atual.
     */
    public function index(): JsonResponse
    {
        $ranking   = $this->leaderboard->getWeeklyLeaderboard();
        $weekStart = UserWeeklyXp::currentWeekStart();

        return $this->success([
            'week_start'   => $weekStart,
            'week_end'     => now()->startOfWeek(\Carbon\Carbon::MONDAY)
                ->addDays(6)->toDateString(),
            'total_users'  => $ranking->count(),
            'leaderboard'  => $ranking,
        ], 'Ranking semanal.');
    }

    /**
     * GET /api/v1/leaderboard/me
     *
     * Posição do usuário autenticado no ranking desta semana.
     * Retorna a posição real mesmo que esteja fora do top 50.
     */
    public function me(): JsonResponse
    {
        $user = auth()->user();
        $rank = $this->leaderboard->getUserRank($user);

        return $this->success($rank, 'Sua posição no ranking desta semana.');
    }
}
