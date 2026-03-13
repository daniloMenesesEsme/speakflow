<?php

namespace App\Http\Controllers\API;

use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;

class SubscriptionController extends BaseController
{
    public function __construct(private SubscriptionService $subscriptions)
    {
    }

    /**
     * GET /api/v1/subscription
     *
     * Retorna o plano ativo do usuário, limites diários e uso atual.
     * Cria automaticamente uma assinatura Free se o usuário não tiver nenhuma.
     */
    public function show(): JsonResponse
    {
        $user  = auth()->user();
        $stats = $this->subscriptions->getUsageStats($user);

        return $this->success($stats, 'Informações da assinatura.');
    }

    /**
     * GET /api/v1/subscription/plans
     *
     * Lista todos os planos disponíveis para upgrade.
     * Não requer autenticação para facilitar exibição na landing page,
     * mas o middleware auth:api está aplicado — ajustar na rota se necessário.
     */
    public function plans(): JsonResponse
    {
        $plans = $this->subscriptions->listPlans();

        return $this->success($plans, 'Planos disponíveis.');
    }
}
