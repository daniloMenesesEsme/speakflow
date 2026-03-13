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
                    'ai_messages_per_day'    => 5,
                    'voice_messages_per_day' => 2,
                    'unlimited'              => false,
                    'corrections'            => true,
                    'leaderboard'            => true,
                    'analytics'              => false,
                    'priority_support'       => false,
                ],
                'is_featured' => false,
                'active'      => true,
            ],

            // ── Pro ──────────────────────────────────────────────────────────
            [
                'name'          => 'Pro',
                'slug'          => 'pro',
                'price'         => 19.90,
                'billing_cycle' => 'monthly',
                'features'      => [
                    'ai_messages_per_day'    => 50,
                    'voice_messages_per_day' => 20,
                    'unlimited'              => false,
                    'corrections'            => true,
                    'leaderboard'            => true,
                    'analytics'              => true,
                    'priority_support'       => false,
                ],
                'is_featured' => true,
                'active'      => true,
            ],

            // ── Premium ──────────────────────────────────────────────────────
            [
                'name'          => 'Premium',
                'slug'          => 'premium',
                'price'         => 34.90,
                'billing_cycle' => 'monthly',
                'features'      => [
                    'ai_messages_per_day'    => 999,
                    'voice_messages_per_day' => 999,
                    'unlimited'              => true,
                    'corrections'            => true,
                    'leaderboard'            => true,
                    'analytics'              => true,
                    'priority_support'       => true,
                ],
                'is_featured' => false,
                'active'      => true,
            ],

            // ── Pro Anual (desconto ~20%) ─────────────────────────────────────
            [
                'name'          => 'Pro Anual',
                'slug'          => 'pro-yearly',
                'price'         => 191.04,
                'billing_cycle' => 'yearly',
                'features'      => [
                    'ai_messages_per_day'    => 50,
                    'voice_messages_per_day' => 20,
                    'unlimited'              => false,
                    'corrections'            => true,
                    'leaderboard'            => true,
                    'analytics'              => true,
                    'priority_support'       => false,
                ],
                'is_featured' => false,
                'active'      => true,
            ],

            // ── Premium Anual ─────────────────────────────────────────────────
            [
                'name'          => 'Premium Anual',
                'slug'          => 'premium-yearly',
                'price'         => 335.04,
                'billing_cycle' => 'yearly',
                'features'      => [
                    'ai_messages_per_day'    => 999,
                    'voice_messages_per_day' => 999,
                    'unlimited'              => true,
                    'corrections'            => true,
                    'leaderboard'            => true,
                    'analytics'              => true,
                    'priority_support'       => true,
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
