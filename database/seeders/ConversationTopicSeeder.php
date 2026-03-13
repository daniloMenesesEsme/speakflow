<?php

namespace Database\Seeders;

use App\Models\ConversationTopic;
use Illuminate\Database\Seeder;

class ConversationTopicSeeder extends Seeder
{
    public function run(): void
    {
        $topics = [
            // ── A1 — Beginner ────────────────────────────────────────────────
            [
                'title'       => 'Greetings & Introductions',
                'slug'        => 'greetings',
                'description' => 'Practice saying hello, introducing yourself, asking names and basic personal information.',
                'level'       => 'A1',
                'icon'        => '👋',
            ],
            [
                'title'       => 'Numbers & Colors',
                'slug'        => 'numbers-colors',
                'description' => 'Learn to count, use ordinal numbers, and describe things using colors.',
                'level'       => 'A1',
                'icon'        => '🎨',
            ],
            [
                'title'       => 'Family',
                'slug'        => 'family',
                'description' => 'Talk about your family members, relationships, and describe people you love.',
                'level'       => 'A1',
                'icon'        => '👨‍👩‍👧‍👦',
            ],

            // ── A2 — Elementary ──────────────────────────────────────────────
            [
                'title'       => 'Food & Restaurant',
                'slug'        => 'food-restaurant',
                'description' => 'Order food, talk about your favorite dishes, and practice restaurant conversations.',
                'level'       => 'A2',
                'icon'        => '🍽️',
            ],
            [
                'title'       => 'Daily Routine',
                'slug'        => 'daily-routine',
                'description' => 'Describe your morning routine, daily habits, and schedule using time expressions.',
                'level'       => 'A2',
                'icon'        => '⏰',
            ],
            [
                'title'       => 'Shopping',
                'slug'        => 'shopping',
                'description' => 'Practice buying clothes, asking prices, and navigating a store in English.',
                'level'       => 'A2',
                'icon'        => '🛍️',
            ],

            // ── B1 — Intermediate ────────────────────────────────────────────
            [
                'title'       => 'Travel & Transport',
                'slug'        => 'travel',
                'description' => 'Discuss trips, book tickets, navigate airports and hotels in English.',
                'level'       => 'B1',
                'icon'        => '✈️',
            ],
            [
                'title'       => 'Work & Career',
                'slug'        => 'work',
                'description' => 'Talk about your job, workplace situations, interviews, and professional goals.',
                'level'       => 'B1',
                'icon'        => '💼',
            ],
            [
                'title'       => 'School & Education',
                'slug'        => 'school',
                'description' => 'Discuss school subjects, study habits, educational experiences and learning goals.',
                'level'       => 'B1',
                'icon'        => '🎓',
            ],
            [
                'title'       => 'Health & Body',
                'slug'        => 'health',
                'description' => 'Talk about health problems, doctor visits, sports, and healthy habits.',
                'level'       => 'B1',
                'icon'        => '🏥',
            ],

            // ── B2 — Upper Intermediate ──────────────────────────────────────
            [
                'title'       => 'Movies & Entertainment',
                'slug'        => 'movies',
                'description' => 'Discuss films, TV shows, music, and pop culture. Share opinions and recommendations.',
                'level'       => 'B2',
                'icon'        => '🎬',
            ],
            [
                'title'       => 'Technology & Social Media',
                'slug'        => 'technology',
                'description' => 'Discuss gadgets, apps, AI, social media trends and their impact on society.',
                'level'       => 'B2',
                'icon'        => '💻',
            ],
            [
                'title'       => 'Environment & Nature',
                'slug'        => 'environment',
                'description' => 'Talk about climate change, sustainability, and ways to protect the planet.',
                'level'       => 'B2',
                'icon'        => '🌿',
            ],

            // ── C1 — Advanced ────────────────────────────────────────────────
            [
                'title'       => 'Business & Economics',
                'slug'        => 'business',
                'description' => 'Discuss business strategies, market trends, entrepreneurship, and financial concepts.',
                'level'       => 'C1',
                'icon'        => '📈',
            ],
            [
                'title'       => 'Politics & Society',
                'slug'        => 'politics',
                'description' => 'Engage in debates about social issues, politics, human rights, and global affairs.',
                'level'       => 'C1',
                'icon'        => '🗳️',
            ],
        ];

        foreach ($topics as $topic) {
            ConversationTopic::updateOrCreate(
                ['slug' => $topic['slug']],
                $topic
            );
        }

        $this->command->info('ConversationTopicSeeder: ' . count($topics) . ' tópicos inseridos/atualizados.');
    }
}
