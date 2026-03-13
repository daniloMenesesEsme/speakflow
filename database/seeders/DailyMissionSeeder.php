<?php

namespace Database\Seeders;

use App\Models\DailyMission;
use Illuminate\Database\Seeder;

class DailyMissionSeeder extends Seeder
{
    public function run(): void
    {
        $missions = [
            // ── Exercícios ───────────────────────────────────────────────────
            [
                'type'        => 'exercise',
                'title'       => 'Pratique 3 Exercícios',
                'description' => 'Responda corretamente 3 exercícios hoje para ganhar XP.',
                'icon'        => '✏️',
                'target'      => 3,
                'xp_reward'   => 30,
                'active'      => true,
            ],

            // ── Lição ────────────────────────────────────────────────────────
            [
                'type'        => 'lesson',
                'title'       => 'Complete 1 Lição',
                'description' => 'Conclua uma lição completa hoje e avance no seu aprendizado.',
                'icon'        => '📚',
                'target'      => 1,
                'xp_reward'   => 50,
                'active'      => true,
            ],

            // ── Conversação ──────────────────────────────────────────────────
            [
                'type'        => 'conversation',
                'title'       => 'Converse com o Tutor',
                'description' => 'Inicie uma conversa com a Mia e pratique seu inglês hoje.',
                'icon'        => '💬',
                'target'      => 1,
                'xp_reward'   => 40,
                'active'      => true,
            ],

            // ── Mensagem de voz ──────────────────────────────────────────────
            [
                'type'        => 'voice_message',
                'title'       => 'Pratique sua Voz',
                'description' => 'Envie 1 mensagem de voz e treine sua pronúncia em inglês.',
                'icon'        => '🎙️',
                'target'      => 1,
                'xp_reward'   => 40,
                'active'      => true,
            ],
        ];

        foreach ($missions as $mission) {
            DailyMission::updateOrCreate(
                ['type' => $mission['type'], 'target' => $mission['target']],
                $mission
            );
        }

        $this->command->info('DailyMissionSeeder: ' . count($missions) . ' missões inseridas/atualizadas.');
    }
}
