<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            // ── Free ─────────────────────────────────────────────────────────
            [
                'name'          => 'Free',
                'slug'          => 'free',
                'price'         => 0.00,
                'billing_cycle' => 'monthly',
                'features'      => [
                    'ai_messages_per_day'    => 10,   // limite solicitado
                    'voice_messages_per_day' => 5,    // equivalente a ~5 min de voz
                    'voice_minutes_per_day'  => 5,
                    'lessons_per_day'        => 2,    // máx. 2 lições geradas por IA/dia
                    'unlimited'              => false,
                    'corrections'            => true,
                    'leaderboard'            => true,
                    'analytics'              => false,
                    'priority_support'       => false,
                    'lesson_generation'      => true,
                ],
                'is_featured' => false,
                'active'      => true,
            ],

            // ── Pro ──────────────────────────────────────────────────────────
            [
                'name'          => 'Pro',
                'slug'          => 'pro',
                'price'         => 9.90,             // valor solicitado
                'billing_cycle' => 'monthly',
                'features'      => [
                    'ai_messages_per_day'    => 999,
                    'voice_messages_per_day' => 999,
                    'voice_minutes_per_day'  => 999,
                    'lessons_per_day'        => 999,
                    'unlimited'              => true,  // ilimitado conforme solicitado
                    'corrections'            => true,
                    'leaderboard'            => true,
                    'analytics'              => true,
                    'priority_support'       => false,
                    'lesson_generation'      => true,
                ],
                'is_featured' => true,
                'active'      => true,
            ],

            // ── Premium ──────────────────────────────────────────────────────
            [
                'name'          => 'Premium',
                'slug'          => 'premium',
                'price'         => 19.90,
                'billing_cycle' => 'monthly',
                'features'      => [
                    'ai_messages_per_day'    => 999,
                    'voice_messages_per_day' => 999,
                    'voice_minutes_per_day'  => 999,
                    'lessons_per_day'        => 999,
                    'unlimited'              => true,
                    'corrections'            => true,
                    'leaderboard'            => true,
                    'analytics'              => true,
                    'priority_support'       => true,
                    'lesson_generation'      => true,
                ],
                'is_featured' => false,
                'active'      => true,
            ],

            // ── Pro Anual ─────────────────────────────────────────────────────
            [
                'name'          => 'Pro Anual',
                'slug'          => 'pro-yearly',
                'price'         => 95.04,            // ~9.90 × 12 × 0.80 (20% off)
                'billing_cycle' => 'yearly',
                'features'      => [
                    'ai_messages_per_day'    => 999,
                    'voice_messages_per_day' => 999,
                    'voice_minutes_per_day'  => 999,
                    'lessons_per_day'        => 999,
                    'unlimited'              => true,
                    'corrections'            => true,
                    'leaderboard'            => true,
                    'analytics'              => true,
                    'priority_support'       => false,
                    'lesson_generation'      => true,
                ],
                'is_featured' => false,
                'active'      => true,
            ],

            // ── Premium Anual ─────────────────────────────────────────────────
            [
                'name'          => 'Premium Anual',
                'slug'          => 'premium-yearly',
                'price'         => 191.04,
                'billing_cycle' => 'yearly',
                'features'      => [
                    'ai_messages_per_day'    => 999,
                    'voice_messages_per_day' => 999,
                    'voice_minutes_per_day'  => 999,
                    'lessons_per_day'        => 999,
                    'unlimited'              => true,
                    'corrections'            => true,
                    'leaderboard'            => true,
                    'analytics'              => true,
                    'priority_support'       => true,
                    'lesson_generation'      => true,
                ],
                'is_featured' => false,
                'active'      => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }

        $this->command->info('PlanSeeder: ' . count($plans) . ' planos inseridos/atualizados.');
    }
}
