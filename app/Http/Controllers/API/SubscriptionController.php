<?php

namespace App\Http\Controllers\API;

use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends BaseController
{
    public function __construct(private SubscriptionService $subscriptions)
    {
    }

    /**
     * GET /api/v1/subscription
     *
     * Retorna plano ativo, limites diários e uso atual (ai, voz, lições).
     */
    public function show(): JsonResponse
    {
        $stats = $this->subscriptions->getUsageStats(auth()->user());

        return $this->success($stats, 'Informações da assinatura.');
    }

    /**
     * GET /api/v1/subscription/plans   (autenticado)
     * GET /api/v1/plans                (alias público — ver rotas)
     *
     * Lista todos os planos disponíveis para upgrade.
     */
    public function plans(): JsonResponse
    {
        $plans = $this->subscriptions->listPlans();

        return $this->success($plans, 'Planos disponíveis.');
    }

    /**
     * POST /api/v1/subscription/upgrade
     *
     * Altera o plano do usuário.
     * Em produção, este endpoint deve validar o pagamento via webhook.
     *
     * Body: { "plan": "pro" }
     */
    public function upgrade(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan' => 'required|string|exists:plans,slug',
        ]);

        $user   = auth()->user();
        $result = $this->subscriptions->upgradePlan($user, $validated['plan']);

        return $this->success($result, $result['message']);
    }

    /**
     * POST /api/v1/subscription/checkout
     *
     * Inicia checkout em provedor de pagamento (Stripe).
     * Body: { "plan": "pro" }
     */
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan' => 'required|string|exists:plans,slug',
        ]);

        $result = $this->subscriptions->createStripeCheckoutSession(auth()->user(), $validated['plan']);

        if (! ($result['success'] ?? false)) {
            return $this->error($result['message'] ?? 'Nao foi possivel iniciar checkout.', null, 422);
        }

        return $this->success($result, 'Checkout iniciado com sucesso.');
    }

    /**
     * POST /api/v1/subscription/webhook/stripe
     *
     * Endpoint publico para confirmacao de pagamento.
     */
    public function stripeWebhook(Request $request): JsonResponse
    {
        $result = $this->subscriptions->processStripeWebhook($request);

        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Webhook invalido.',
            ], $result['status'] ?? 400);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? 'Webhook processado.',
        ], $result['status'] ?? 200);
    }

    /**
     * GET /api/v1/subscription/logs
     *
     * Histórico de uso do usuário: ai_message, voice_message, lesson_generation.
     * Parâmetro: ?days=30 (padrão 30 dias)
     */
    public function logs(Request $request): JsonResponse
    {
        $days = min((int) $request->query('days', 30), 90);
        $data = $this->subscriptions->getUsageLogs(auth()->user(), $days);

        return $this->success($data, 'Histórico de uso.');
    }
}
