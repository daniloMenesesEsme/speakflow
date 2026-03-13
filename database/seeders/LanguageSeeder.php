<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $languages = [
            ['name' => 'English',    'code' => 'en', 'flag_emoji' => '🇺🇸'],
            ['name' => 'Portuguese', 'code' => 'pt', 'flag_emoji' => '🇧🇷'],
            ['name' => 'Spanish',    'code' => 'es', 'flag_emoji' => '🇪🇸'],
            ['name' => 'French',     'code' => 'fr', 'flag_emoji' => '🇫🇷'],
            ['name' => 'German',     'code' => 'de', 'flag_emoji' => '🇩🇪'],
            ['name' => 'Italian',    'code' => 'it', 'flag_emoji' => '🇮🇹'],
            ['name' => 'Japanese',   'code' => 'ja', 'flag_emoji' => '🇯🇵'],
        ];

        foreach ($languages as $language) {
            Language::firstOrCreate(['code' => $language['code']], $language);
        }
    }
}
