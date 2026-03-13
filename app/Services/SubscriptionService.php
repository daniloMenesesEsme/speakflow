<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageLimit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    // ─── API pública ─────────────────────────────────────────────────────────

    /**
     * Retorna o plano ativo do usuário.
     * Se não houver assinatura ativa, cria e retorna o plano Free automaticamente.
     */
    public function getUserPlan(User $user): Plan
    {
        $subscription = Subscription::with('plan')
            ->forUser($user->id)
            ->active()
            ->latest('started_at')
            ->first();

        if ($subscription) {
            return $subscription->plan;
        }

        // Garante que o usuário tenha uma assinatura Free
        return $this->assignFreePlan($user);
    }

    /**
     * Retorna a assinatura ativa do usuário (ou cria free se não existir).
     */
    public function getActiveSubscription(User $user): Subscription
    {
        $subscription = Subscription::with('plan')
            ->forUser($user->id)
            ->active()
            ->latest('started_at')
            ->first();

        if ($subscription) {
            return $subscription;
        }

        $this->assignFreePlan($user);

        return Subscription::with('plan')
            ->forUser($user->id)
            ->active()
            ->latest()
            ->firstOrFail();
    }

    /**
     * Verifica se o usuário ainda pode enviar mensagens de IA hoje.
     */
    public function checkAiLimit(User $user): bool
    {
        $plan  = $this->getUserPlan($user);
        $limit = $plan->getAiLimit();

        if ($limit === PHP_INT_MAX) {
            return true; // plano ilimitado
        }

        $usage = UsageLimit::todayFor($user->id);

        return $usage->ai_messages < $limit;
    }

    /**
     * Incrementa o contador de mensagens de IA do dia.
     */
    public function incrementAiUsage(User $user): void
    {
        UsageLimit::todayFor($user->id);

        UsageLimit::where('user_id', $user->id)
            ->where('date', now()->toDateString())
            ->increment('ai_messages');
    }

    /**
     * Verifica se o usuário ainda pode enviar mensagens de voz hoje.
     */
    public function checkVoiceLimit(User $user): bool
    {
        $plan  = $this->getUserPlan($user);
        $limit = $plan->getVoiceLimit();

        if ($limit === PHP_INT_MAX) {
            return true;
        }

        $usage = UsageLimit::todayFor($user->id);

        return $usage->voice_messages < $limit;
    }

    /**
     * Incrementa o contador de mensagens de voz do dia.
     */
    public function incrementVoiceUsage(User $user): void
    {
        UsageLimit::todayFor($user->id);

        UsageLimit::where('user_id', $user->id)
            ->where('date', now()->toDateString())
            ->increment('voice_messages');
    }

    /**
     * Retorna estatísticas de uso + limites do dia para o endpoint /subscription.
     */
    public function getUsageStats(User $user): array
    {
        $plan  = $this->getUserPlan($user);
        $sub   = $this->getActiveSubscription($user);
        $usage = UsageLimit::todayFor($user->id);

        $aiLimit    = $plan->getAiLimit();
        $voiceLimit = $plan->getVoiceLimit();

        return [
            'plan' => [
                'id'            => $plan->id,
                'name'          => $plan->name,
                'slug'          => $plan->slug,
                'price'         => $plan->price,
                'billing_cycle' => $plan->billing_cycle,
                'features'      => $plan->features,
                'is_unlimited'  => $plan->isUnlimited(),
            ],
            'subscription' => [
                'status'     => $sub->status,
                'started_at' => $sub->started_at->toDateString(),
                'expires_at' => $sub->expires_at?->toDateString(),
                'days_left'  => $sub->daysUntilExpiry(),
                'is_active'  => $sub->isActive(),
            ],
            'usage_today' => [
                'date'                 => now()->toDateString(),
                'ai_messages_used'     => $usage->ai_messages,
                'ai_messages_limit'    => $aiLimit === PHP_INT_MAX ? null : $aiLimit,
                'ai_messages_left'     => $aiLimit === PHP_INT_MAX
                    ? null
                    : max(0, $aiLimit - $usage->ai_messages),
                'voice_messages_used'  => $usage->voice_messages,
                'voice_messages_limit' => $voiceLimit === PHP_INT_MAX ? null : $voiceLimit,
                'voice_messages_left'  => $voiceLimit === PHP_INT_MAX
                    ? null
                    : max(0, $voiceLimit - $usage->voice_messages),
            ],
        ];
    }

    /**
     * Lista todos os planos disponíveis para exibição na tela de upgrade.
     */
    public function listPlans(): array
    {
        return Plan::active()
            ->orderBy('price')
            ->get()
            ->map(fn ($plan) => [
                'id'            => $plan->id,
                'name'          => $plan->name,
                'slug'          => $plan->slug,
                'price'         => $plan->price,
                'billing_cycle' => $plan->billing_cycle,
                'features'      => $plan->features,
                'is_featured'   => $plan->is_featured,
                'is_unlimited'  => $plan->isUnlimited(),
            ])
            ->toArray();
    }

    // ─── Internos ─────────────────────────────────────────────────────────────

    /**
     * Atribui o plano Free ao usuário e retorna o Plan.
     */
    private function assignFreePlan(User $user): Plan
    {
        $freePlan = Plan::where('slug', Plan::FREE)->firstOrFail();

        DB::transaction(function () use ($user, $freePlan) {
            // Cancela assinaturas anteriores expiradas (limpeza)
            Subscription::forUser($user->id)
                ->where('status', Subscription::STATUS_EXPIRED)
                ->update(['status' => Subscription::STATUS_CANCELED]);

            Subscription::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'plan_id' => $freePlan->id,
                    'status'  => Subscription::STATUS_ACTIVE,
                ],
                [
                    'started_at' => now(),
                    'expires_at' => null, // Free = sem expiração
                ]
            );
        });

        return $freePlan;
    }
}
