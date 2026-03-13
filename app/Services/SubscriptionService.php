<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageLimit;
use App\Models\UsageLog;
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

    // ─── API unificada (nova) ─────────────────────────────────────────────────

    /**
     * Verifica se o usuário pode usar uma feature hoje.
     *
     * Features: 'ai_message' | 'voice_message' | 'lesson_generation'
     */
    public function canUseFeature(User $user, string $feature): bool
    {
        $plan  = $this->getUserPlan($user);
        $limit = $plan->getLimitFor($feature);

        if ($limit === PHP_INT_MAX) {
            return true;
        }

        $usage = UsageLimit::todayFor($user->id);

        $used = match ($feature) {
            Plan::FEATURE_AI_MESSAGE        => $usage->ai_messages,
            Plan::FEATURE_VOICE_MESSAGE     => $usage->voice_messages,
            Plan::FEATURE_LESSON_GENERATION => $usage->lesson_generation,
            default                         => 0,
        };

        return $used < $limit;
    }

    /**
     * Registra o uso de uma feature:
     *  1. Incrementa o contador diário (usage_limits)
     *  2. Grava o evento no log permanente (usage_logs)
     *
     * @param  array $metadata  Dados extras opcionais (ex: lesson_id, conversation_id)
     */
    public function registerUsage(User $user, string $feature, array $metadata = []): void
    {
        $plan = $this->getUserPlan($user);

        // ── 1. Incrementar contador diário ────────────────────────────────────
        UsageLimit::todayFor($user->id);

        $column = match ($feature) {
            Plan::FEATURE_AI_MESSAGE        => 'ai_messages',
            Plan::FEATURE_VOICE_MESSAGE     => 'voice_messages',
            Plan::FEATURE_LESSON_GENERATION => 'lesson_generation',
            default                         => null,
        };

        if ($column) {
            UsageLimit::where('user_id', $user->id)
                ->where('date', now()->toDateString())
                ->increment($column);
        }

        // ── 2. Registrar evento de log permanente ─────────────────────────────
        UsageLog::create([
            'user_id'   => $user->id,
            'type'      => $feature,
            'quantity'  => 1,
            'metadata'  => $metadata ?: null,
            'plan_slug' => $plan->slug,
        ]);
    }

    /**
     * Retorna o histórico de uso do usuário, com totais por tipo.
     */
    public function getUsageLogs(User $user, int $days = 30): array
    {
        $since = now()->subDays($days)->startOfDay();

        $logs = UsageLog::forUser($user->id)
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->get();

        $totals = $logs->groupBy('type')->map(fn ($g) => [
            'total'    => $g->sum('quantity'),
            'today'    => $g->filter(fn ($l) => $l->created_at->isToday())->sum('quantity'),
            'this_week'=> $g->filter(fn ($l) => $l->created_at->isCurrentWeek())->sum('quantity'),
        ]);

        return [
            'period_days' => $days,
            'totals'      => $totals,
            'events'      => $logs->map(fn ($l) => [
                'id'         => $l->id,
                'type'       => $l->type,
                'quantity'   => $l->quantity,
                'plan_slug'  => $l->plan_slug,
                'created_at' => $l->created_at->toISOString(),
            ])->toArray(),
        ];
    }

    /**
     * Simula upgrade de plano (estrutura pronta para integração com gateway de pagamento).
     * Por ora, altera diretamente o plano do usuário.
     */
    public function upgradePlan(User $user, string $planSlug): array
    {
        $plan = Plan::where('slug', $planSlug)->where('active', true)->firstOrFail();

        DB::transaction(function () use ($user, $plan) {
            // Cancela assinatura ativa atual
            Subscription::forUser($user->id)
                ->active()
                ->update(['status' => Subscription::STATUS_CANCELED]);

            // Cria nova assinatura
            Subscription::create([
                'user_id'    => $user->id,
                'plan_id'    => $plan->id,
                'status'     => Subscription::STATUS_ACTIVE,
                'started_at' => now(),
                'expires_at' => $plan->billing_cycle === 'yearly'
                    ? now()->addYear()
                    : ($plan->price > 0 ? now()->addMonth() : null),
                'notes'      => 'Manual upgrade via API',
            ]);
        });

        return [
            'success'  => true,
            'plan'     => $plan->name,
            'slug'     => $plan->slug,
            'price'    => $plan->price,
            'started_at' => now()->toDateString(),
            'message'  => "Plano atualizado para {$plan->name} com sucesso.",
        ];
    }

    /**
     * Retorna estatísticas de uso + limites do dia para o endpoint /subscription.
     */
    public function getUsageStats(User $user): array
    {
        $plan        = $this->getUserPlan($user);
        $sub         = $this->getActiveSubscription($user);
        $usage       = UsageLimit::todayFor($user->id);

        $aiLimit     = $plan->getAiLimit();
        $voiceLimit  = $plan->getVoiceLimit();
        $lessonLimit = $plan->getLessonLimit();

        $inf = fn ($limit, $used) => $limit === PHP_INT_MAX
            ? ['limit' => null, 'used' => $used, 'left' => null]
            : ['limit' => $limit, 'used' => $used, 'left' => max(0, $limit - $used)];

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
                'date'              => now()->toDateString(),
                'ai_messages'       => $inf($aiLimit,     $usage->ai_messages),
                'voice_messages'    => $inf($voiceLimit,  $usage->voice_messages),
                'lesson_generation' => $inf($lessonLimit, $usage->lesson_generation),
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
