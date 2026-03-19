<?php

namespace Database\Seeders;

use App\Models\PlacementQuestion;
use Illuminate\Database\Seeder;

class PlacementQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $questions = [
            // A1
            ['question' => 'What ___ your name?', 'options' => ['am', 'is', 'are', 'be'], 'correct_answer' => 'is', 'skill' => 'grammar', 'cefr_level' => 'A1', 'display_order' => 1],
            ['question' => 'She ___ from Brazil.', 'options' => ['am', 'is', 'are', 'be'], 'correct_answer' => 'is', 'skill' => 'grammar', 'cefr_level' => 'A1', 'display_order' => 2],
            ['question' => 'Choose the correct word: "I have a ___".', 'options' => ['dog', 'beautiful', 'run', 'quickly'], 'correct_answer' => 'dog', 'skill' => 'vocabulary', 'cefr_level' => 'A1', 'display_order' => 3],

            // A2
            ['question' => 'I ___ to the gym yesterday.', 'options' => ['go', 'went', 'gone', 'going'], 'correct_answer' => 'went', 'skill' => 'grammar', 'cefr_level' => 'A2', 'display_order' => 4],
            ['question' => 'How long ___ you lived here?', 'options' => ['did', 'do', 'have', 'are'], 'correct_answer' => 'have', 'skill' => 'grammar', 'cefr_level' => 'A2', 'display_order' => 5],
            ['question' => 'Choose the synonym of "happy".', 'options' => ['sad', 'angry', 'glad', 'tired'], 'correct_answer' => 'glad', 'skill' => 'vocabulary', 'cefr_level' => 'A2', 'display_order' => 6],

            // B1
            ['question' => 'If I ___ more time, I would learn French.', 'options' => ['have', 'had', 'has', 'having'], 'correct_answer' => 'had', 'skill' => 'grammar', 'cefr_level' => 'B1', 'display_order' => 7],
            ['question' => 'She asked me where I ___ from.', 'options' => ['come', 'came', 'coming', 'have come'], 'correct_answer' => 'came', 'skill' => 'grammar', 'cefr_level' => 'B1', 'display_order' => 8],
            ['question' => 'Reading: "Tom missed the bus, so he arrived late." Why was Tom late?', 'options' => ['He woke up early', 'He missed the bus', 'He took a taxi', 'He was sick'], 'correct_answer' => 'He missed the bus', 'skill' => 'reading', 'cefr_level' => 'B1', 'display_order' => 9],

            // B2
            ['question' => 'By the time we arrived, the movie ___.', 'options' => ['started', 'had started', 'has started', 'starts'], 'correct_answer' => 'had started', 'skill' => 'grammar', 'cefr_level' => 'B2', 'display_order' => 10],
            ['question' => 'Choose the best connector: "I was tired, ___ I kept studying."', 'options' => ['because', 'although', 'so', 'but'], 'correct_answer' => 'but', 'skill' => 'vocabulary', 'cefr_level' => 'B2', 'display_order' => 11],
            ['question' => 'Reading: "The project was delayed due to supply issues." What caused the delay?', 'options' => ['Bad management', 'Supply issues', 'Lack of staff', 'Client changes'], 'correct_answer' => 'Supply issues', 'skill' => 'reading', 'cefr_level' => 'B2', 'display_order' => 12],
        ];

        foreach ($questions as $item) {
            PlacementQuestion::updateOrCreate(
                ['question' => $item['question']],
                array_merge($item, ['is_active' => true, 'weight' => 1.0]),
            );
        }
    }
}

