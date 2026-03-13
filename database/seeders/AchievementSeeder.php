<?php

namespace Database\Seeders;

use App\Models\Achievement;
use Illuminate\Database\Seeder;

class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        $achievements = [
            // ── Exercícios ────────────────────────────────────────────────────
            [
                'title'           => 'Primeiro Exercício',
                'description'     => 'Responda seu primeiro exercício corretamente.',
                'xp_reward'       => 10,
                'icon'            => '✏️',
                'category'        => 'milestone',
                'condition_type'  => 'exercises_completed',
                'condition_value' => 1,
            ],
            [
                'title'           => 'Praticante',
                'description'     => 'Responda 10 exercícios corretamente.',
                'xp_reward'       => 30,
                'icon'            => '💪',
                'category'        => 'milestone',
                'condition_type'  => 'exercises_completed',
                'condition_value' => 10,
            ],
            [
                'title'           => 'Exercitando Muito',
                'description'     => 'Responda 50 exercícios corretamente.',
                'xp_reward'       => 80,
                'icon'            => '🧠',
                'category'        => 'milestone',
                'condition_type'  => 'exercises_completed',
                'condition_value' => 50,
            ],

            // ── Lições ────────────────────────────────────────────────────────
            [
                'title'           => 'Primeira Lição',
                'description'     => 'Complete sua primeira lição.',
                'xp_reward'       => 50,
                'icon'            => '🎯',
                'category'        => 'milestone',
                'condition_type'  => 'first_lesson',
                'condition_value' => 1,
            ],
            [
                'title'           => 'Estudante Dedicado',
                'description'     => 'Complete 5 lições.',
                'xp_reward'       => 100,
                'icon'            => '📚',
                'category'        => 'milestone',
                'condition_type'  => 'lessons_completed',
                'condition_value' => 5,
            ],
            [
                'title'           => 'Mestre das Lições',
                'description'     => 'Complete 20 lições.',
                'xp_reward'       => 300,
                'icon'            => '🏆',
                'category'        => 'milestone',
                'condition_type'  => 'lessons_completed',
                'condition_value' => 20,
            ],

            // Streaks
            [
                'title'           => 'Três em Sequência',
                'description'     => 'Estude 3 dias consecutivos.',
                'xp_reward'       => 30,
                'icon'            => '🔥',
                'category'        => 'streak',
                'condition_type'  => 'study_streak',
                'condition_value' => 3,
            ],
            [
                'title'           => 'Semana Perfeita',
                'description'     => 'Estude 7 dias consecutivos.',
                'xp_reward'       => 100,
                'icon'            => '⚡',
                'category'        => 'streak',
                'condition_type'  => 'study_streak',
                'condition_value' => 7,
            ],
            [
                'title'           => 'Mês de Dedicação',
                'description'     => 'Estude 30 dias consecutivos.',
                'xp_reward'       => 500,
                'icon'            => '💎',
                'category'        => 'streak',
                'condition_type'  => 'study_streak',
                'condition_value' => 30,
            ],

            // XP
            [
                'title'           => 'Primeiros Pontos',
                'description'     => 'Acumule 100 XP.',
                'xp_reward'       => 10,
                'icon'            => '⭐',
                'category'        => 'xp',
                'condition_type'  => 'total_xp',
                'condition_value' => 100,
            ],
            [
                'title'           => 'Acumulador',
                'description'     => 'Acumule 1000 XP.',
                'xp_reward'       => 50,
                'icon'            => '🌟',
                'category'        => 'xp',
                'condition_type'  => 'total_xp',
                'condition_value' => 1000,
            ],
            [
                'title'           => 'XP Master',
                'description'     => 'Acumule 5000 XP.',
                'xp_reward'       => 200,
                'icon'            => '💫',
                'category'        => 'xp',
                'condition_type'  => 'total_xp',
                'condition_value' => 5000,
            ],

            // Pronúncia
            [
                'title'           => 'Pronúncia Excelente',
                'description'     => 'Obtenha nota A (90+) em pronúncia.',
                'xp_reward'       => 75,
                'icon'            => '🎤',
                'category'        => 'pronunciation',
                'condition_type'  => 'pronunciation_score',
                'condition_value' => 90,
            ],

            // Meta diária
            [
                'title'           => 'Meta Atingida',
                'description'     => 'Atinja sua meta diária por 7 dias.',
                'xp_reward'       => 150,
                'icon'            => '🎯',
                'category'        => 'consistency',
                'condition_type'  => 'daily_goal',
                'condition_value' => 7,
            ],
        ];

        foreach ($achievements as $achievement) {
            Achievement::firstOrCreate(
                ['title' => $achievement['title']],
                $achievement
            );
        }
    }
}
