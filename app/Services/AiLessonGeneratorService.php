<?php

namespace App\Services;

use App\Models\AiUsageLog;
use App\Models\GeneratedExercise;
use App\Models\GeneratedLesson;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AiLessonGeneratorService
{
    // ─── Configuração ────────────────────────────────────────────────────────

    private const MODEL          = 'gpt-4o-mini';
    private const MAX_EXERCISES  = 5;
    private const TEMPERATURE    = 0.7;

    // XP padrão por tipo de exercício
    private const XP_BY_TYPE = [
        'fill_blank'          => 10,
        'multiple_choice'     => 10,
        'sentence_correction' => 15,
        'translation'         => 15,
        'true_false'          => 5,
    ];

    // ─── Constructor ─────────────────────────────────────────────────────────

    public function __construct(
        private AdaptiveTutorService $adaptiveTutor,
    ) {
    }

    // ─── API pública ─────────────────────────────────────────────────────────

    /**
     * Gera uma lição completa com exercícios usando OpenAI ou fallback offline.
     * Salva automaticamente em generated_lessons + generated_exercises.
     *
     * @param  User        $user
     * @param  string      $level         ex: 'A2'
     * @param  string      $topic         ex: 'Food and Restaurant'
     * @param  array       $grammarFocus  ex: ['want to + infinitive']
     * @return array       Estrutura da lição com exercícios
     */
    public function generateLesson(
        User   $user,
        string $level,
        string $topic,
        array  $grammarFocus = []
    ): array {
        $apiKey = config('services.openai.key') ?? env('OPENAI_API_KEY');

        $result = $apiKey
            ? $this->generateViaOpenAi($apiKey, $level, $topic, $grammarFocus)
            : $this->generateOffline($level, $topic, $grammarFocus);

        // Persistir lição e exercícios
        $lesson = $this->persist($user, $level, $topic, $grammarFocus, $result);

        // Registrar uso de tokens se veio da OpenAI
        if ($result['driver'] === GeneratedLesson::DRIVER_OPENAI && $result['total_tokens'] > 0) {
            AiUsageLog::create([
                'user_id'         => $user->id,
                'conversation_id' => null,
                'model'           => self::MODEL,
                'prompt_tokens'   => $result['prompt_tokens'],
                'completion_tokens'=> $result['completion_tokens'],
                'total_tokens'    => $result['total_tokens'],
                'estimated_cost'  => AiUsageLog::calculateCost(
                    self::MODEL,
                    $result['prompt_tokens'],
                    $result['completion_tokens']
                ),
            ]);
        }

        return $this->formatResponse($lesson);
    }

    /**
     * Retorna o histórico de lições geradas para o usuário.
     */
    public function getHistory(User $user, int $perPage = 10): array
    {
        $lessons = GeneratedLesson::forUser($user->id)
            ->with('exercises')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return [
            'data' => $lessons->map(fn ($l) => $this->formatResponse($l))->toArray(),
            'meta' => [
                'current_page' => $lessons->currentPage(),
                'per_page'     => $lessons->perPage(),
                'total'        => $lessons->total(),
                'last_page'    => $lessons->lastPage(),
            ],
        ];
    }

    /**
     * Retorna uma lição gerada específica pelo ID.
     */
    public function getLesson(int $lessonId, User $user): ?array
    {
        $lesson = GeneratedLesson::forUser($user->id)
            ->with('exercises')
            ->find($lessonId);

        return $lesson ? $this->formatResponse($lesson) : null;
    }

    // ─── Geração via OpenAI ──────────────────────────────────────────────────

    private function generateViaOpenAi(
        string $apiKey,
        string $level,
        string $topic,
        array  $grammarFocus
    ): array {
        try {
            $client = \OpenAI::factory()
                ->withApiKey($apiKey)
                ->make();

            $systemPrompt = $this->buildSystemPrompt($level);
            $userPrompt   = $this->buildUserPrompt($level, $topic, $grammarFocus);

            $response = $client->chat()->create([
                'model'       => self::MODEL,
                'temperature' => self::TEMPERATURE,
                'messages'    => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userPrompt],
                ],
                'response_format' => ['type' => 'json_object'],
            ]);

            $content        = $response->choices[0]->message->content;
            $promptTokens   = $response->usage->promptTokens ?? 0;
            $completionTokens = $response->usage->completionTokens ?? 0;
            $totalTokens    = $response->usage->totalTokens ?? 0;

            $parsed = $this->parseOpenAiResponse($content, $level, $topic, $grammarFocus);
            $parsed['driver']            = GeneratedLesson::DRIVER_OPENAI;
            $parsed['prompt_tokens']     = $promptTokens;
            $parsed['completion_tokens'] = $completionTokens;
            $parsed['total_tokens']      = $totalTokens;

            return $parsed;

        } catch (\Throwable $e) {
            Log::warning('AiLessonGeneratorService: OpenAI falhou, usando fallback.', [
                'error' => $e->getMessage(),
            ]);

            return $this->generateOffline($level, $topic, $grammarFocus);
        }
    }

    // ─── Geração offline (fallback sem chave de API) ──────────────────────────

    private function generateOffline(
        string $level,
        string $topic,
        array  $grammarFocus
    ): array {
        $focus = !empty($grammarFocus) ? $grammarFocus[0] : 'basic vocabulary';

        $templates = $this->getOfflineTemplates($level, $topic, $focus);

        return [
            'lesson_title'        => $templates['title'],
            'lesson_introduction' => $templates['introduction'],
            'exercises'           => $templates['exercises'],
            'driver'              => GeneratedLesson::DRIVER_OFFLINE,
            'prompt_tokens'       => 0,
            'completion_tokens'   => 0,
            'total_tokens'        => 0,
        ];
    }

    // ─── Prompts ─────────────────────────────────────────────────────────────

    private function buildSystemPrompt(string $level): string
    {
        return <<<PROMPT
You are an expert English language teacher creating educational content for a mobile learning app.
Your students are learning English at CEFR level {$level}.
You must generate structured lesson content in valid JSON format only.
The exercises must be appropriate for level {$level} learners.
Always respond with a JSON object — no markdown, no extra text.
PROMPT;
    }

    private function buildUserPrompt(string $level, string $topic, array $grammarFocus): string
    {
        $focusStr  = !empty($grammarFocus)
            ? 'Grammar focus: ' . implode(', ', $grammarFocus) . '.'
            : 'No specific grammar focus.';

        $exerciseTypes = implode(', ', [
            'fill_blank (fill in the blank)',
            'multiple_choice (4 options)',
            'sentence_correction (find and correct the error)',
        ]);

        return <<<PROMPT
Create an English lesson for level {$level} about the topic "{$topic}".
{$focusStr}

Return a JSON object with this exact structure:
{
  "lesson_title": "string (engaging title)",
  "lesson_introduction": "string (2-3 sentence overview for the student)",
  "exercises": [
    {
      "type": "fill_blank|multiple_choice|sentence_correction",
      "order": 1,
      "question": "string (instruction for the student)",
      "sentence": "string (the sentence with ___ for fill_blank, or full sentence for correction)",
      "correct_answer": "string",
      "options": ["array", "of", "4", "strings"] or null,
      "hint": "string (optional short hint)" or null,
      "explanation": "string (why this answer is correct, educational)"
    }
  ]
}

Rules:
- Generate exactly 5 exercises.
- Use all 3 exercise types (at least 1 of each).
- Keep sentences short and practical for level {$level}.
- For fill_blank: use ___ in the sentence field.
- For multiple_choice: always provide exactly 4 options; put the correct one among them.
- For sentence_correction: give a sentence with one clear grammatical error to fix.
- Explanations must be educational and specific to the grammar focus.
- All content must be in English.
PROMPT;
    }

    // ─── Parsing da resposta OpenAI ──────────────────────────────────────────

    private function parseOpenAiResponse(
        string $content,
        string $level,
        string $topic,
        array  $grammarFocus
    ): array {
        $data = json_decode($content, true);

        if (!$data || !isset($data['lesson_title'], $data['exercises'])) {
            Log::warning('AiLessonGeneratorService: JSON inválido da OpenAI, usando fallback.', [
                'content' => substr($content, 0, 200),
            ]);
            return $this->generateOffline($level, $topic, $grammarFocus);
        }

        $exercises = [];
        foreach (array_slice($data['exercises'], 0, self::MAX_EXERCISES) as $i => $ex) {
            $type = $ex['type'] ?? 'multiple_choice';
            if (!in_array($type, GeneratedExercise::TYPES)) {
                $type = 'multiple_choice';
            }

            $exercises[] = [
                'type'           => $type,
                'order'          => ($ex['order'] ?? $i + 1),
                'question'       => $ex['question']       ?? 'Answer the following:',
                'sentence'       => $ex['sentence']       ?? null,
                'correct_answer' => $ex['correct_answer'] ?? '',
                'options'        => isset($ex['options']) && is_array($ex['options'])
                    ? array_slice($ex['options'], 0, 4)
                    : null,
                'hint'           => $ex['hint']        ?? null,
                'explanation'    => $ex['explanation'] ?? null,
                'xp_reward'      => self::XP_BY_TYPE[$type] ?? 10,
            ];
        }

        return [
            'lesson_title'        => $data['lesson_title'] ?? "English Lesson: {$topic}",
            'lesson_introduction' => $data['lesson_introduction'] ?? null,
            'exercises'           => $exercises,
        ];
    }

    // ─── Templates offline ────────────────────────────────────────────────────

    private function getOfflineTemplates(string $level, string $topic, string $focus): array
    {
        $topicSlug = strtolower(str_replace([' ', '&', '-'], '_', $topic));

        // Banco de templates por tópico
        $bank = [
            'food' => [
                'title'        => "Eating Out: Food & Restaurant Vocabulary ({$level})",
                'introduction' => "In this lesson, you will practice talking about food, ordering in a restaurant, and expressing food preferences. Focus on using correct verb forms when talking about what you want to eat.",
                'exercises'    => [
                    [
                        'type'           => 'fill_blank',
                        'order'          => 1,
                        'question'       => 'Complete the sentence with the correct verb form.',
                        'sentence'       => 'I would like ___ a table for two, please.',
                        'correct_answer' => 'to book',
                        'options'        => null,
                        'hint'           => 'Use "to + infinitive" after "would like".',
                        'explanation'    => 'After "would like", we always use "to + infinitive". Example: "I would like to order."',
                        'xp_reward'      => 10,
                    ],
                    [
                        'type'           => 'multiple_choice',
                        'order'          => 2,
                        'question'       => 'Which sentence is grammatically correct?',
                        'sentence'       => null,
                        'correct_answer' => 'I want to eat the pasta.',
                        'options'        => [
                            'I want eat the pasta.',
                            'I want to eat the pasta.',
                            'I wanting eat the pasta.',
                            'I wants to eat the pasta.',
                        ],
                        'hint'           => 'Remember: "want" is followed by "to + infinitive".',
                        'explanation'    => '"Want" requires "to + infinitive": "I want to eat." Never use "want + base verb" directly.',
                        'xp_reward'      => 10,
                    ],
                    [
                        'type'           => 'sentence_correction',
                        'order'          => 3,
                        'question'       => 'Find and correct the grammatical error.',
                        'sentence'       => 'The waiter bring our food after twenty minutes.',
                        'correct_answer' => 'The waiter brought our food after twenty minutes.',
                        'options'        => null,
                        'hint'           => 'This sentence is in the past tense.',
                        'explanation'    => '"Bring" is an irregular verb. Past tense: bring → brought.',
                        'xp_reward'      => 15,
                    ],
                    [
                        'type'           => 'fill_blank',
                        'order'          => 4,
                        'question'       => 'Complete the question correctly.',
                        'sentence'       => '___ the soup of the day?',
                        'correct_answer' => 'What is',
                        'options'        => null,
                        'hint'           => 'Use a question word + "to be".',
                        'explanation'    => 'To ask about options, use "What is..." or "What are...".',
                        'xp_reward'      => 10,
                    ],
                    [
                        'type'           => 'multiple_choice',
                        'order'          => 5,
                        'question'       => 'How do you politely ask for the bill?',
                        'sentence'       => null,
                        'correct_answer' => 'Could we have the bill, please?',
                        'options'        => [
                            'Give me the bill.',
                            'Could we have the bill, please?',
                            'I want bill now.',
                            'We need pay.',
                        ],
                        'hint'           => 'Use a polite modal verb.',
                        'explanation'    => '"Could we have..." is a polite way to request something. It\'s more formal than "Can we have...".',
                        'xp_reward'      => 10,
                    ],
                ],
            ],
            'greetings' => [
                'title'        => "Greetings & Introductions in English ({$level})",
                'introduction' => "In this lesson, you will practice how to greet people, introduce yourself, and respond to introductions in English. These are essential phrases for everyday communication.",
                'exercises'    => [
                    [
                        'type'           => 'fill_blank',
                        'order'          => 1,
                        'question'       => 'Complete the greeting.',
                        'sentence'       => '___ to meet you!',
                        'correct_answer' => 'Nice',
                        'options'        => null,
                        'hint'           => 'A common phrase when meeting someone for the first time.',
                        'explanation'    => '"Nice to meet you" is a standard greeting when being introduced to someone new.',
                        'xp_reward'      => 10,
                    ],
                    [
                        'type'           => 'multiple_choice',
                        'order'          => 2,
                        'question'       => 'What is the correct response to "How are you?"',
                        'sentence'       => null,
                        'correct_answer' => "I'm fine, thank you.",
                        'options'        => [
                            "I'm fine, thank you.",
                            "I am 25 years.",
                            "My name is João.",
                            "Yes, I am.",
                        ],
                        'hint'           => null,
                        'explanation'    => '"I\'m fine, thank you" is the standard positive response. "And you?" can be added to be polite.',
                        'xp_reward'      => 10,
                    ],
                    [
                        'type'           => 'sentence_correction',
                        'order'          => 3,
                        'question'       => 'Correct the error in this introduction.',
                        'sentence'       => 'My name are John and I am from Brazil.',
                        'correct_answer' => 'My name is John and I am from Brazil.',
                        'options'        => null,
                        'hint'           => 'Check the verb "to be" after "my name".',
                        'explanation'    => '"My name" is singular, so use "is" not "are": "My name is John."',
                        'xp_reward'      => 15,
                    ],
                    [
                        'type'           => 'fill_blank',
                        'order'          => 4,
                        'question'       => 'Complete with the correct form of "to be".',
                        'sentence'       => 'I ___ a student from São Paulo.',
                        'correct_answer' => 'am',
                        'options'        => null,
                        'hint'           => '"I" uses which form of "to be"?',
                        'explanation'    => 'With "I", always use "am": I am, I\'m. Never "I is" or "I are".',
                        'xp_reward'      => 10,
                    ],
                    [
                        'type'           => 'multiple_choice',
                        'order'          => 5,
                        'question'       => 'How do you correctly state your age in English?',
                        'sentence'       => null,
                        'correct_answer' => 'I am 30 years old.',
                        'options'        => [
                            'I have 30 years.',
                            'I am 30 years old.',
                            'I got 30 years.',
                            'My age is have 30.',
                        ],
                        'hint'           => 'In English, age uses the verb "to be".',
                        'explanation'    => 'In English, age = "I am X years old." In Portuguese we say "tenho X anos" but in English we use "to be", not "to have".',
                        'xp_reward'      => 10,
                    ],
                ],
            ],
        ];

        // Selecionar template por palavras-chave do tópico
        $selected = null;
        foreach ($bank as $key => $template) {
            if (str_contains($topicSlug, $key)) {
                $selected = $template;
                break;
            }
        }

        // Fallback genérico se nenhum template se encaixar
        if (!$selected) {
            $selected = $this->buildGenericTemplate($level, $topic, $focus);
        }

        return $selected;
    }

    private function buildGenericTemplate(string $level, string $topic, string $focus): array
    {
        return [
            'title'        => "English Practice: {$topic} ({$level})",
            'introduction' => "In this lesson, you will practice English related to the topic \"{$topic}\". Pay special attention to: {$focus}.",
            'exercises'    => [
                [
                    'type'           => 'multiple_choice',
                    'order'          => 1,
                    'question'       => 'Which sentence uses the correct verb form?',
                    'sentence'       => null,
                    'correct_answer' => 'She wants to learn English.',
                    'options'        => [
                        'She want learn English.',
                        'She wants to learn English.',
                        'She wanting learn English.',
                        'She want to learns English.',
                    ],
                    'hint'           => 'Third person singular + want to + infinitive.',
                    'explanation'    => '"Want" in third person singular adds -s: "wants". It is followed by "to + infinitive".',
                    'xp_reward'      => 10,
                ],
                [
                    'type'           => 'fill_blank',
                    'order'          => 2,
                    'question'       => 'Complete the sentence.',
                    'sentence'       => 'They ___ studying English for two years.',
                    'correct_answer' => 'have been',
                    'options'        => null,
                    'hint'           => 'Present perfect continuous tense.',
                    'explanation'    => 'For an action that started in the past and continues now, use "have/has been + verb-ing".',
                    'xp_reward'      => 10,
                ],
                [
                    'type'           => 'sentence_correction',
                    'order'          => 3,
                    'question'       => 'Find and correct the error in this sentence.',
                    'sentence'       => 'Yesterday, I go to the market and buy some fruits.',
                    'correct_answer' => 'Yesterday, I went to the market and bought some fruits.',
                    'options'        => null,
                    'hint'           => '"Yesterday" indicates past tense.',
                    'explanation'    => 'When using "yesterday", all verbs must be in past tense: go → went, buy → bought.',
                    'xp_reward'      => 15,
                ],
                [
                    'type'           => 'multiple_choice',
                    'order'          => 4,
                    'question'       => 'Choose the correct preposition.',
                    'sentence'       => 'I have been living here ___ 2020.',
                    'correct_answer' => 'since',
                    'options'        => ['for', 'since', 'from', 'at'],
                    'hint'           => 'Is 2020 a duration or a point in time?',
                    'explanation'    => 'Use "since" with a specific point in time (2020, Monday, January). Use "for" with a duration (2 years, 3 months).',
                    'xp_reward'      => 10,
                ],
                [
                    'type'           => 'fill_blank',
                    'order'          => 5,
                    'question'       => 'Complete using the correct form.',
                    'sentence'       => 'I am looking forward ___ meeting you.',
                    'correct_answer' => 'to',
                    'options'        => null,
                    'hint'           => '"Look forward to" is a fixed expression.',
                    'explanation'    => '"Look forward to" is always followed by a noun or gerund (-ing form): "looking forward to meeting".',
                    'xp_reward'      => 10,
                ],
            ],
        ];
    }

    // ─── Persistência ────────────────────────────────────────────────────────

    private function persist(
        User   $user,
        string $level,
        string $topic,
        array  $grammarFocus,
        array  $result
    ): GeneratedLesson {
        return DB::transaction(function () use ($user, $level, $topic, $grammarFocus, $result) {
            $lesson = GeneratedLesson::create([
                'user_id'             => $user->id,
                'level'               => $level,
                'topic'               => $topic,
                'grammar_focus'       => $grammarFocus,
                'lesson_title'        => $result['lesson_title'],
                'lesson_introduction' => $result['lesson_introduction'] ?? null,
                'driver'              => $result['driver'],
                'prompt_tokens'       => $result['prompt_tokens'],
                'completion_tokens'   => $result['completion_tokens'],
                'estimated_cost'      => isset($result['prompt_tokens'])
                    ? AiUsageLog::calculateCost(
                        self::MODEL,
                        $result['prompt_tokens'],
                        $result['completion_tokens']
                    )
                    : 0,
            ]);

            foreach ($result['exercises'] as $ex) {
                GeneratedExercise::create(array_merge($ex, ['lesson_id' => $lesson->id]));
            }

            return $lesson->load('exercises');
        });
    }

    // ─── Formatação ───────────────────────────────────────────────────────────

    private function formatResponse(GeneratedLesson $lesson): array
    {
        return [
            'id'                  => $lesson->id,
            'lesson_title'        => $lesson->lesson_title,
            'lesson_introduction' => $lesson->lesson_introduction,
            'level'               => $lesson->level,
            'topic'               => $lesson->topic,
            'grammar_focus'       => $lesson->grammar_focus,
            'driver'              => $lesson->driver,
            'exercises_count'     => $lesson->exercises->count(),
            'total_xp'            => $lesson->exercises->sum('xp_reward'),
            'exercises'           => $lesson->exercises->map(fn ($ex) => [
                'id'             => $ex->id,
                'type'           => $ex->type,
                'order'          => $ex->order,
                'question'       => $ex->question,
                'sentence'       => $ex->sentence,
                'correct_answer' => $ex->correct_answer,
                'options'        => $ex->options,
                'hint'           => $ex->hint,
                'explanation'    => $ex->explanation,
                'xp_reward'      => $ex->xp_reward,
            ])->toArray(),
            'usage' => [
                'driver'           => $lesson->driver,
                'prompt_tokens'    => $lesson->prompt_tokens,
                'completion_tokens'=> $lesson->completion_tokens,
                'estimated_cost'   => $lesson->estimated_cost,
            ],
            'created_at' => $lesson->created_at?->toISOString(),
        ];
    }
}
