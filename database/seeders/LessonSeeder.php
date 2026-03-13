<?php

namespace Database\Seeders;

use App\Models\Language;
use App\Models\Lesson;
use App\Models\Phrase;
use App\Models\Exercise;
use Illuminate\Database\Seeder;

class LessonSeeder extends Seeder
{
    public function run(): void
    {
        $english = Language::where('code', 'en')->first();

        if (!$english) {
            return;
        }

        $lessonsData = [
            [
                'title'       => 'Greetings & Introductions',
                'level' => 'A1',
                'category'    => 'Daily Life',
                'order'       => 1,
                'description' => 'Learn how to greet people and introduce yourself in English.',
                'xp_reward'   => 20,
                'phrases'     => [
                    ['english_text' => 'Hello, how are you?',     'portuguese_text' => 'Olá, como você está?',     'difficulty' => 'easy',   'phonetic' => 'həˈloʊ haʊ ɑr juː'],
                    ['english_text' => 'My name is...',            'portuguese_text' => 'Meu nome é...',             'difficulty' => 'easy',   'phonetic' => 'maɪ neɪm ɪz'],
                    ['english_text' => 'Nice to meet you.',        'portuguese_text' => 'Prazer em conhecê-lo.',     'difficulty' => 'easy',   'phonetic' => 'naɪs tuː miːt juː'],
                    ['english_text' => 'Where are you from?',      'portuguese_text' => 'De onde você é?',           'difficulty' => 'medium', 'phonetic' => 'wɛr ɑr juː frɒm'],
                    ['english_text' => 'I am from Brazil.',        'portuguese_text' => 'Eu sou do Brasil.',         'difficulty' => 'easy',   'phonetic' => 'aɪ æm frɒm brəˈzɪl'],
                ],
                'exercises'   => [
                    [
                        'type'           => 'multiple_choice',
                        'question'       => 'How do you say "Olá, como vai?" in English?',
                        'correct_answer' => 'Hello, how are you?',
                        'options'        => ['Hello, how are you?', 'Goodbye!', 'Thank you!', 'Good night!'],
                        'difficulty'     => 'easy',
                        'order'          => 1,
                        'points'         => 10,
                    ],
                    [
                        'type'           => 'fill_in_blank',
                        'question'       => 'Complete: "Nice to ___ you."',
                        'correct_answer' => 'meet',
                        'options'        => ['meet', 'see', 'know', 'find'],
                        'difficulty'     => 'easy',
                        'order'          => 2,
                        'points'         => 10,
                    ],
                    [
                        'type'           => 'translation',
                        'question'       => 'Translate: "Meu nome é João."',
                        'correct_answer' => 'My name is João.',
                        'options'        => null,
                        'difficulty'     => 'easy',
                        'order'          => 3,
                        'points'         => 15,
                    ],
                ],
            ],
            [
                'title'       => 'Numbers & Counting',
                'level' => 'A1',
                'category'    => 'Basics',
                'order'       => 2,
                'description' => 'Learn numbers from 1 to 100 and how to count in English.',
                'xp_reward'   => 15,
                'phrases'     => [
                    ['english_text' => 'One, two, three',          'portuguese_text' => 'Um, dois, três',            'difficulty' => 'easy',   'phonetic' => 'wʌn tuː θriː'],
                    ['english_text' => 'How many?',                'portuguese_text' => 'Quantos?',                  'difficulty' => 'easy',   'phonetic' => 'haʊ ˈmɛni'],
                    ['english_text' => 'I have ten books.',        'portuguese_text' => 'Eu tenho dez livros.',       'difficulty' => 'medium', 'phonetic' => 'aɪ hæv tɛn bʊks'],
                ],
                'exercises'   => [
                    [
                        'type'           => 'multiple_choice',
                        'question'       => 'What is the English word for "cinco"?',
                        'correct_answer' => 'five',
                        'options'        => ['four', 'five', 'six', 'seven'],
                        'difficulty'     => 'easy',
                        'order'          => 1,
                        'points'         => 10,
                    ],
                    [
                        'type'           => 'translation',
                        'question'       => 'Translate: "Eu tenho vinte anos."',
                        'correct_answer' => 'I am twenty years old.',
                        'options'        => null,
                        'difficulty'     => 'medium',
                        'order'          => 2,
                        'points'         => 15,
                    ],
                ],
            ],
            [
                'title'       => 'Colors & Descriptions',
                'level' => 'A1',
                'category'    => 'Vocabulary',
                'order'       => 3,
                'description' => 'Learn how to describe objects using colors and adjectives.',
                'xp_reward'   => 15,
                'phrases'     => [
                    ['english_text' => 'The sky is blue.',         'portuguese_text' => 'O céu é azul.',             'difficulty' => 'easy',   'phonetic' => 'ðə skaɪ ɪz bluː'],
                    ['english_text' => 'My car is red.',           'portuguese_text' => 'Meu carro é vermelho.',      'difficulty' => 'easy',   'phonetic' => 'maɪ kɑr ɪz rɛd'],
                    ['english_text' => 'What color is it?',        'portuguese_text' => 'Qual é a cor?',              'difficulty' => 'medium', 'phonetic' => 'wɒt ˈkʌlər ɪz ɪt'],
                ],
                'exercises'   => [
                    [
                        'type'           => 'multiple_choice',
                        'question'       => 'What color is "vermelho" in English?',
                        'correct_answer' => 'red',
                        'options'        => ['blue', 'green', 'red', 'yellow'],
                        'difficulty'     => 'easy',
                        'order'          => 1,
                        'points'         => 10,
                    ],
                ],
            ],
            [
                'title'       => 'Past Tense Basics',
                'level' => 'A2',
                'category'    => 'Grammar',
                'order'       => 1,
                'description' => 'Learn how to talk about past events using simple past tense.',
                'xp_reward'   => 25,
                'phrases'     => [
                    ['english_text' => 'I went to the store yesterday.',     'portuguese_text' => 'Fui à loja ontem.',               'difficulty' => 'medium', 'phonetic' => 'aɪ wɛnt tuː ðə stɔr ˈjɛstərdeɪ'],
                    ['english_text' => 'She called me last night.',          'portuguese_text' => 'Ela me ligou ontem à noite.',       'difficulty' => 'medium', 'phonetic' => 'ʃiː kɔːld miː læst naɪt'],
                    ['english_text' => 'We watched a movie.',                'portuguese_text' => 'Nós assistimos a um filme.',        'difficulty' => 'medium', 'phonetic' => 'wiː wɒtʃt ə ˈmuːvi'],
                ],
                'exercises'   => [
                    [
                        'type'           => 'fill_in_blank',
                        'question'       => 'Yesterday I ___ (go) to school.',
                        'correct_answer' => 'went',
                        'options'        => ['went', 'go', 'gone', 'going'],
                        'difficulty'     => 'medium',
                        'order'          => 1,
                        'points'         => 15,
                    ],
                    [
                        'type'           => 'translation',
                        'question'       => 'Translate: "Eu estudei inglês ontem."',
                        'correct_answer' => 'I studied English yesterday.',
                        'options'        => null,
                        'difficulty'     => 'medium',
                        'order'          => 2,
                        'points'         => 20,
                    ],
                ],
            ],
        ];

        foreach ($lessonsData as $lessonData) {
            $phrasesData   = $lessonData['phrases'];
            $exercisesData = $lessonData['exercises'];
            unset($lessonData['phrases'], $lessonData['exercises']);

            $lesson = Lesson::firstOrCreate(
                ['language_id' => $english->id, 'title' => $lessonData['title']],
                array_merge($lessonData, ['language_id' => $english->id])
            );

            foreach ($phrasesData as $phraseData) {
                Phrase::firstOrCreate(
                    ['lesson_id' => $lesson->id, 'english_text' => $phraseData['english_text']],
                    array_merge($phraseData, ['lesson_id' => $lesson->id])
                );
            }

            foreach ($exercisesData as $exerciseData) {
                Exercise::firstOrCreate(
                    ['lesson_id' => $lesson->id, 'question' => $exerciseData['question']],
                    array_merge($exerciseData, ['lesson_id' => $lesson->id])
                );
            }
        }
    }
}
