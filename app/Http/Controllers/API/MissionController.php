<?php

namespace App\Http\Controllers\API;

use App\Services\DailyMissionService;
use Illuminate\Http\JsonResponse;

class MissionController extends BaseController
{
    public function __construct(private DailyMissionService $missions)
    {
    }

    /**
     * GET /api/v1/missions/today
     *
     * Retorna as 4 missões diárias com o progresso atual do usuário.
     * Cria automaticamente os registros se for o primeiro acesso do dia.
     */
    public function today(): JsonResponse
    {
        $user     = auth()->user();
        $missions = $this->missions->getTodayMissions($user);

        $completedCount = collect($missions)->where('completed', true)->count();

        return $this->success([
            'date'            => now()->toDateString(),
            'completed_count' => $completedCount,
            'total'           => count($missions),
            'missions'        => $missions,
        ], 'Missões do dia.');
    }

    /**
     * GET /api/v1/missions/progress
     *
     * Resumo do progresso diário: missões concluídas, XP ganho,
     * taxa de conclusão e detalhamento por missão.
     */
    public function progress(): JsonResponse
    {
        $user     = auth()->user();
        $progress = $this->missions->getDailyProgress($user);

        return $this->success($progress, 'Progresso das missões diárias.');
    }

    /**
     * GET /api/v1/missions/check
     *
     * Verifica quais missões estão concluídas e quais ainda estão pendentes.
     * Útil para notificações push no app mobile.
     */
    public function check(): JsonResponse
    {
        $user   = auth()->user();
        $status = $this->missions->checkCompletion($user);

        return $this->success($status, 'Status das missões.');
    }
}
