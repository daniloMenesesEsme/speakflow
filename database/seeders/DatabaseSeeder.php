<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            LanguageSeeder::class,
            LessonSeeder::class,
            DialogueSeeder::class,
            AchievementSeeder::class,
            ConversationTopicSeeder::class,
            DailyMissionSeeder::class,
            PlacementQuestionSeeder::class,
        ]);

        User::factory()->create([
            'name'               => 'Demo User',
            'email'              => 'demo@speakflow.com',
            'native_language'    => 'pt',
            'target_language'    => 'en',
            'level'              => 'A1',
            'daily_goal_minutes' => 15,
        ]);
    }
}
