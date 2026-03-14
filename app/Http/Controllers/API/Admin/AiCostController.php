<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\User;
use App\Services\AiCostAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiCostController extends BaseController
{
    public function __construct(private readonly AiCostAnalyticsService $analytics) {}

    /**
     * GET /api/admin/ai-cost/today
     * Custo de IA gerado no dia atual.
     */
    public function today(): JsonResponse
    {
        $data = $this->analytics->getTodayCost();

        return $this->success($data, 'Custo de IA de hoje.');
    }

    /**
     * GET /api/admin/ai-cost/trend?days=30
     * Tendência diária de custos nos últimos N dias.
     */
    public function trend(Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 30);
        $days = max(1, min($days, 365));

        $data = $this->analytics->getDailyCostTrend($days);

        return $this->success($data, "Tendência de custo dos últimos {$days} dias.");
    }

    /**
     * GET /api/admin/ai-cost/by-feature
     * Breakdown de custo por funcionalidade (chat vs lesson_generation).
     */
    public function byFeature(): JsonResponse
    {
        $data = $this->analytics->getCostByFeature();

        return $this->success($data, 'Custo por funcionalidade.');
    }

    /**
     * GET /api/admin/ai-cost/by-user/{id}
     * Custo total e breakdown de um usuário específico.
     */
    public function byUser(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $data = $this->analytics->getCostByUser($user->id);

        $data['user'] = [
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'level' => $user->level,
        ];

        return $this->success($data, "Custo do usuário #{$id}.");
    }
}
