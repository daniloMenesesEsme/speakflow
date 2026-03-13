<?php

namespace App\Services;

use App\Models\AiConversation;
use App\Models\AiCorrection;
use App\Models\AiMessage;
use App\Models\AiUsageLog;
use App\Models\ConversationTopic;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use OpenAI;

class AiTutorService
{
    private const MODEL          = 'gpt-4o-mini';
    private const MAX_TOKENS     = 300;
    private const TEMPERATURE    = 0.7;
    private const MAX_HISTORY    = 20;    // mensagens anteriores enviadas ao contexto
    private const TOPIC_DEFAULT  = 'general conversation';

    // ─── API pública ────────────────────────────────────────────────────────

    /**
     * Processa uma mensagem do usuário dentro de uma conversa existente
     * (ou cria uma nova) e retorna a resposta do tutor virtual com dados de uso.
     *
     * @return array{reply: string, conversation_id: int, model: string, usage: array, correction: array|null, topic: array|null}
     */
    public function chat(
        User    $user,
        string  $userMessage,
        ?int    $conversationId = null,
        ?string $topic          = null,
        ?int    $topicId        = null,
    ): array {
        // ── Resolver tópico estruturado (se enviado topic_id) ─────────────────
        $topicModel = $topicId
            ? ConversationTopic::active()->find($topicId)
            : null;

        // ── Localizar ou criar conversa ──────────────────────────────────────
        $conversation = $conversationId
            ? AiConversation::where('id', $conversationId)
                ->where('user_id', $user->id)
                ->firstOrFail()
            : AiConversation::create([
                'user_id'  => $user->id,
                'language' => $user->target_language ?? 'en',
                'topic'    => $topicModel?->title ?? $topic ?? self::TOPIC_DEFAULT,
                'topic_id' => $topicModel?->id,
                'level'    => $user->level ?? 'A1',
            ]);

        // ── Persistir mensagem do usuário ────────────────────────────────────
        AiMessage::create([
            'conversation_id' => $conversation->id,
            'role'            => 'user',
            'content'         => $userMessage,
        ]);

        // ── Chamar OpenAI (ou fallback se sem chave) ─────────────────────────
        // Retorna: [reply, promptTokens, completionTokens, totalTokens, model]
        [$reply, $promptTokens, $completionTokens, $totalTokens, $model]
            = $this->getReply($conversation, $userMessage);

        // ── Calcular custo estimado ───────────────────────────────────────────
        $estimatedCost = AiUsageLog::calculateCost($model, $promptTokens, $completionTokens);

        // ── Persistir resposta do tutor ──────────────────────────────────────
        AiMessage::create([
            'conversation_id' => $conversation->id,
            'role'            => 'assistant',
            'content'         => $reply,
            'tokens'          => $totalTokens,
            'model'           => $model,
        ]);

        // ── Salvar log de uso (apenas para chamadas reais à API) ─────────────
        if ($model !== 'fallback' && $totalTokens > 0) {
            AiUsageLog::create([
                'user_id'           => $user->id,
                'conversation_id'   => $conversation->id,
                'model'             => $model,
                'prompt_tokens'     => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens'      => $totalTokens,
                'estimated_cost'    => $estimatedCost,
            ]);
        }

        // ── Atualizar metadados da conversa ──────────────────────────────────
        $conversation->increment('messages_count', 2);
        $conversation->increment('total_tokens', $totalTokens);
        $conversation->update(['last_message_at' => now()]);

        // ── Análise gramatical paralela (apenas quando há API key) ───────────
        $correction = $this->analyzeGrammar(
            user:           $user,
            conversation:   $conversation,
            userMessage:    $userMessage,
            model:          $model,
        );

        // ── Carregar tópico da conversa para retornar ao app ────────────────
        $loadedTopic = $conversation->conversationTopic ?? $topicModel;

        return [
            'reply'           => $reply,
            'conversation_id' => $conversation->id,
            'model'           => $model,
            'topic'           => $loadedTopic ? [
                'id'          => $loadedTopic->id,
                'title'       => $loadedTopic->title,
                'description' => $loadedTopic->description,
                'level'       => $loadedTopic->level,
                'icon'        => $loadedTopic->icon,
            ] : null,
            'usage'           => [
                'prompt_tokens'     => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens'      => $totalTokens,
                'estimated_cost'    => $estimatedCost,
            ],
            'correction'      => $correction,
        ];
    }

    /**
     * Analisa a mensagem do usuário em busca de erros gramaticais.
     * Se a OPENAI_API_KEY não estiver configurada, usa heurística offline.
     * Salva o resultado em ai_corrections somente quando há erro.
     *
     * @return array{original: string, corrected: string, explanation: string}|null
     */
    public function analyzeGrammar(
        User            $user,
        AiConversation  $conversation,
        string          $userMessage,
        string          $model,
    ): ?array {
        // Mensagens muito curtas (saudações, "yes", "ok") não precisam de correção
        if (mb_strlen(trim($userMessage)) < 8) {
            return null;
        }

        $apiKey = config('services.openai.key');

        $result = $apiKey && $model !== 'fallback'
            ? $this->openAiGrammarCheck($apiKey, $userMessage, $conversation->language ?? 'en')
            : $this->heuristicGrammarCheck($userMessage);

        if ($result === null) {
            return null;
        }

        // Persistir somente erros reais
        AiCorrection::create([
            'user_id'         => $user->id,
            'conversation_id' => $conversation->id,
            'original_text'   => $userMessage,
            'corrected_text'  => $result['corrected'],
            'explanation'     => $result['explanation'],
            'language'        => $conversation->language ?? 'en',
        ]);

        return [
            'original'    => $userMessage,
            'corrected'   => $result['corrected'],
            'explanation' => $result['explanation'],
        ];
    }

    /**
     * Retorna o histórico de correções do usuário.
     */
    public function getCorrections(User $user, int $perPage = 20): array
    {
        $corrections = AiCorrection::forUser($user->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return [
            'total'       => $corrections->total(),
            'per_page'    => $corrections->perPage(),
            'current_page'=> $corrections->currentPage(),
            'last_page'   => $corrections->lastPage(),
            'data'        => collect($corrections->items())->map(fn ($c) => [
                'id'              => $c->id,
                'original'        => $c->original_text,
                'corrected'       => $c->corrected_text,
                'explanation'     => $c->explanation,
                'language'        => $c->language,
                'conversation_id' => $c->conversation_id,
                'created_at'      => $c->created_at->toISOString(),
            ])->toArray(),
        ];
    }

    // ─── Internos: gramática ─────────────────────────────────────────────────

    /**
     * Chama a OpenAI com um prompt específico de verificação gramatical.
     * Retorna null se a frase estiver correta, ou [corrected, explanation] se houver erro.
     */
    private function openAiGrammarCheck(
        string $apiKey,
        string $userMessage,
        string $language,
    ): ?array {
        try {
            $client = OpenAI::client($apiKey);

            $systemPrompt = <<<PROMPT
            You are a grammar checker for {$language} language learners.
            Analyze the user's message for grammar, spelling, or vocabulary mistakes.
            
            Rules:
            - If the message is grammatically CORRECT: respond with exactly the word: OK
            - If there are mistakes: respond with a JSON object in this exact format:
              {"corrected": "...", "explanation": "..."}
            - The "corrected" field must have the full corrected sentence.
            - The "explanation" must be SHORT (1-2 sentences), in English, educational tone.
            - Focus on the most important mistake only.
            - Do NOT add markdown, backticks or extra text.
            PROMPT;

            $response = $client->chat()->create([
                'model'       => self::MODEL,
                'messages'    => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userMessage],
                ],
                'max_tokens'  => 150,
                'temperature' => 0.2,
            ]);

            $content = trim($response->choices[0]->message->content ?? '');

            if (strtoupper($content) === 'OK') {
                return null;
            }

            $parsed = json_decode($content, true);

            if (! isset($parsed['corrected'], $parsed['explanation'])) {
                return null;
            }

            // Ignorar se a correção for idêntica ao original
            if (strtolower($parsed['corrected']) === strtolower($userMessage)) {
                return null;
            }

            return [
                'corrected'   => $parsed['corrected'],
                'explanation' => $parsed['explanation'],
            ];

        } catch (\Throwable $e) {
            Log::warning('AiTutorService: grammar check falhou — ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Verificação heurística offline de erros comuns em inglês.
     * Cobre os erros mais frequentes cometidos por falantes de português.
     */
    private function heuristicGrammarCheck(string $userMessage): ?array
    {
        $text  = $userMessage;
        $lower = strtolower($text);

        $rules = [
            // Ausência de "to be"
            [
                'pattern'     => '/\bI\s+(happy|sad|tired|hungry|good|bad|fine|ready|here|there)\b/i',
                'replace'     => fn ($m) => 'I am ' . strtolower($m[1]),
                'explanation' => 'Missing "to be" verb. Use "I am + adjective/location" in English.',
            ],
            // "I goes / he go" — concordância sujeito-verbo
            [
                'pattern'     => '/\bI\s+goes\b/i',
                'replace'     => fn ($m) => 'I go',
                'explanation' => 'With "I", use the base form of the verb: "I go", not "I goes".',
            ],
            [
                'pattern'     => '/\b(he|she|it)\s+go\b/i',
                'replace'     => fn ($m) => $m[1] . ' goes',
                'explanation' => 'With "he/she/it", add -s to the verb: "he goes", not "he go".',
            ],
            // "I have 20 years" / "I have 20 years old" (tradução literal de "tenho 20 anos")
            [
                'pattern'     => '/\bI have (\d+) years(?: old)?\b/i',
                'replace'     => fn ($m) => 'I am ' . $m[1] . ' years old',
                'explanation' => 'In English, say "I am [age] years old", not "I have [age] years".',
            ],
            // "make a question" → "ask a question"
            [
                'pattern'     => '/\bmake a question\b/i',
                'replace'     => fn ($m) => 'ask a question',
                'explanation' => 'In English, we "ask a question", not "make a question".',
            ],
            // double negatives: "I don't know nothing"
            [
                'pattern'     => '/\bdon\'t know nothing\b/i',
                'replace'     => fn ($m) => "don't know anything",
                'explanation' => 'Avoid double negatives. Use "I don\'t know anything" instead.',
            ],
            // "I want eat" → "I want to eat"
            [
                'pattern'     => '/\bI want (eat|go|learn|practice|study|buy|see|visit)\b/i',
                'replace'     => fn ($m) => 'I want to ' . strtolower($m[1]),
                'explanation' => 'Use "want to + infinitive". Example: "I want to eat", not "I want eat".',
            ],
        ];

        foreach ($rules as $rule) {
            if (preg_match($rule['pattern'], $text, $matches)) {
                $corrected = preg_replace_callback(
                    $rule['pattern'],
                    $rule['replace'],
                    $text
                );

                if ($corrected !== $text) {
                    return [
                        'corrected'   => $corrected,
                        'explanation' => $rule['explanation'],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Retorna estatísticas de uso de IA do usuário.
     */
    public function getUsageStats(User $user): array
    {
        $allTime  = AiUsageLog::forUser($user->id);
        $thisMonth = (clone $allTime)->thisMonth();

        $perModel = AiUsageLog::forUser($user->id)
            ->selectRaw('model, COUNT(*) as requests, SUM(total_tokens) as tokens, SUM(estimated_cost) as cost')
            ->groupBy('model')
            ->get()
            ->map(fn ($r) => [
                'model'          => $r->model,
                'requests'       => (int)   $r->requests,
                'total_tokens'   => (int)   $r->tokens,
                'estimated_cost' => (float) $r->cost,
            ]);

        return [
            'all_time' => [
                'total_requests'  => $allTime->count(),
                'total_tokens'    => (int)   $allTime->sum('total_tokens'),
                'estimated_cost'  => (float) round($allTime->sum('estimated_cost'), 6),
            ],
            'this_month' => [
                'total_requests'  => $thisMonth->count(),
                'total_tokens'    => (int)   $thisMonth->sum('total_tokens'),
                'estimated_cost'  => (float) round($thisMonth->sum('estimated_cost'), 6),
            ],
            'per_model'   => $perModel,
            'conversations_count' => $user->aiConversations()->count(),
        ];
    }

    /**
     * Retorna as conversas recentes do usuário para o app mobile.
     */
    public function getConversations(User $user, int $limit = 10): array
    {
        return $user->aiConversations()
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->limit($limit)
            ->get()
            ->map(fn ($c) => [
                'id'             => $c->id,
                'topic'          => $c->topic,
                'language'       => $c->language,
                'level'          => $c->level,
                'messages_count' => $c->messages_count,
                'last_message_at'=> $c->last_message_at?->toISOString(),
                'created_at'     => $c->created_at->toISOString(),
            ])
            ->toArray();
    }

    /**
     * Retorna o histórico de mensagens de uma conversa específica.
     */
    public function getHistory(AiConversation $conversation): array
    {
        return $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->get()
            ->map(fn ($m) => [
                'role'       => $m->role,
                'content'    => $m->content,
                'created_at' => $m->created_at->toISOString(),
            ])
            ->toArray();
    }

    // ─── Internos ────────────────────────────────────────────────────────────

    /**
     * Chama a API da OpenAI e retorna:
     * [reply, promptTokens, completionTokens, totalTokens, model]
     *
     * Se a chave não estiver configurada ou ocorrer erro, usa fallback offline.
     */
    private function getReply(AiConversation $conversation, string $userMessage): array
    {
        $apiKey = config('services.openai.key');

        if (empty($apiKey)) {
            Log::info('AiTutorService: OPENAI_API_KEY não configurada, usando fallback offline.');
            return $this->fallbackReply($userMessage, $conversation);
        }

        try {
            $client   = OpenAI::client($apiKey);
            $messages = $this->buildMessages($conversation, $userMessage);

            $response = $client->chat()->create([
                'model'       => self::MODEL,
                'messages'    => $messages,
                'max_tokens'  => self::MAX_TOKENS,
                'temperature' => self::TEMPERATURE,
            ]);

            $reply             = $response->choices[0]->message->content;
            $promptTokens      = $response->usage->promptTokens     ?? 0;
            $completionTokens  = $response->usage->completionTokens ?? 0;
            $totalTokens       = $response->usage->totalTokens      ?? 0;

            return [$reply, $promptTokens, $completionTokens, $totalTokens, self::MODEL];

        } catch (\Throwable $e) {
            Log::error('AiTutorService: erro na chamada OpenAI — ' . $e->getMessage());
            return $this->fallbackReply($userMessage, $conversation);
        }
    }

    /**
     * Monta o array de mensagens para a API OpenAI.
     * Inclui system prompt de tutor + histórico recente + nova mensagem.
     */
    private function buildMessages(AiConversation $conversation, string $userMessage): array
    {
        $systemPrompt = $this->buildSystemPrompt($conversation);
        $history      = $conversation->toOpenAiHistory(self::MAX_HISTORY);

        // Remove a última mensagem do usuário (já está em $userMessage)
        if (! empty($history) && end($history)['role'] === 'user') {
            array_pop($history);
        }

        return array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $history,
            [['role' => 'user',   'content' => $userMessage]],
        );
    }

    /**
     * System prompt personalizado com base no nível e idioma da conversa.
     */
    private function buildSystemPrompt(AiConversation $conversation): string
    {
        $levelDescriptions = [
            'A1' => 'complete beginner — use very simple words, short sentences, present tense only',
            'A2' => 'elementary — use simple vocabulary and basic grammar',
            'B1' => 'intermediate — use everyday vocabulary, some idioms are okay',
            'B2' => 'upper intermediate — use varied vocabulary and natural expressions',
            'C1' => 'advanced — use rich vocabulary, complex structures, idiomatic language',
            'C2' => 'mastery — use native-level language naturally',
        ];

        $level    = $conversation->level ?? 'A1';
        $levelDesc = $levelDescriptions[$level] ?? $levelDescriptions['A1'];
        $language  = strtoupper($conversation->language ?? 'EN');

        // Carregar o tópico estruturado, se houver (eager load se ainda não carregado)
        $topicModel       = $conversation->relationLoaded('conversationTopic')
            ? $conversation->conversationTopic
            : $conversation->conversationTopic()->first();

        $topicTitle       = $topicModel?->title ?? $conversation->topic ?? self::TOPIC_DEFAULT;
        $topicDescription = $topicModel?->description ?? '';

        // Instrução extra de tópico, incluída somente quando há um tópico estruturado
        $topicInstruction = $topicModel
            ? "The conversation topic is: \"{$topicTitle}\". {$topicDescription}\n" .
              "Guide the student to talk about this topic naturally. " .
              "Use relevant vocabulary from this topic throughout the conversation."
            : "The conversation topic is: \"{$topicTitle}\".";

        return <<<PROMPT
        You are an encouraging and patient {$language} language tutor named "Mia" 🌟.
        
        Student level: {$level} ({$levelDesc})
        {$topicInstruction}
        
        Your rules:
        1. ALWAYS respond in {$language} only (never switch to the student's native language).
        2. Adapt your vocabulary and sentence complexity to the student's {$level} level.
        3. Keep responses SHORT (2–4 sentences max) and conversational.
        4. If the student makes a grammar mistake, gently correct it ONCE at the end of your reply: "💡 Quick tip: ..."
        5. Ask one follow-up question related to the topic to keep the conversation going.
        6. Be warm, encouraging, and natural — like a friendly tutor, not a textbook.
        7. If the student writes in their native language, reply in {$language} and gently remind them to practice in {$language}.
        8. Stay focused on the topic "{$topicTitle}" throughout the conversation.
        PROMPT;
    }

    /**
     * Respostas offline contextuais para quando a API não está disponível.
     * Detecta saudações, perguntas e fornece uma resposta plausível em inglês.
     */
    private function fallbackReply(string $userMessage, AiConversation $conversation): array
    {
        $msg   = strtolower(trim($userMessage));
        $topic = strtolower($conversation->topic ?? '');

        // Incorporar o slug do tópico estruturado para melhorar o match
        $topicSlug = $conversation->conversationTopic?->slug ?? '';

        $reply = match (true) {
            str_contains($msg, 'hello') || str_contains($msg, 'hi') || str_contains($msg, 'hey')
                => "Hi there! 😊 I'm Mia, your English tutor. Great to meet you! What would you like to practice today?",

            str_contains($msg, 'how are you')
                => "I'm doing great, thank you for asking! 😄 How about you? How's your day going?",

            str_contains($msg, 'my name') || str_contains($msg, 'i am') || str_contains($msg, "i'm")
                => "Nice to meet you! 🌟 It's wonderful that you're practicing English. What topics are you most interested in?",

            str_contains($msg, 'thank') || str_contains($msg, 'thanks')
                => "You're very welcome! 😊 Keep up the great work — you're doing amazing! Any other questions?",

            str_contains($msg, 'bye') || str_contains($msg, 'goodbye')
                => "Goodbye! 👋 It was great chatting with you. Come back soon to keep practicing!",

            str_contains($msg, '?')
                => "That's a great question! 🤔 In English, we have many ways to express that idea. Could you tell me more about what you mean?",

            // Tópicos estruturados (slugs)
            in_array($topicSlug, ['food-restaurant']) || str_contains($topic, 'restaurant') || str_contains($msg, 'food') || str_contains($msg, 'eat')
                => "Great topic! 🍽️ When you're at a restaurant, it's useful to say 'Could I have the menu, please?' What food do you enjoy most?",

            in_array($topicSlug, ['travel']) || str_contains($topic, 'travel') || str_contains($msg, 'travel') || str_contains($msg, 'trip')
                => "Traveling is wonderful! ✈️ In English, you might say 'I would like to book a ticket to...' Where would you like to go?",

            in_array($topicSlug, ['work']) || str_contains($topic, 'work') || str_contains($msg, 'work') || str_contains($msg, 'job')
                => "Work vocabulary is very important! 💼 You can say 'I work as a...' or 'My job involves...' What do you do for work?",

            in_array($topicSlug, ['family']) || str_contains($topic, 'family') || str_contains($msg, 'family') || str_contains($msg, 'brother') || str_contains($msg, 'sister')
                => "Family is a wonderful topic! 👨‍👩‍👧‍👦 In English you can say 'I have two brothers and one sister.' Tell me about your family!",

            in_array($topicSlug, ['movies']) || str_contains($topic, 'movie') || str_contains($msg, 'movie') || str_contains($msg, 'film')
                => "Movies are great for learning! 🎬 You can say 'My favorite movie is...' or 'I recently watched...' What kind of movies do you enjoy?",

            in_array($topicSlug, ['technology']) || str_contains($topic, 'tech') || str_contains($msg, 'phone') || str_contains($msg, 'computer') || str_contains($msg, 'app')
                => "Technology is everywhere! 💻 You might say 'I use my phone to...' or 'My favorite app is...' How does technology help you every day?",

            in_array($topicSlug, ['school']) || str_contains($topic, 'school') || str_contains($msg, 'study') || str_contains($msg, 'learn')
                => "Education is so important! 🎓 You can say 'I study at...' or 'My favorite subject is...' What do you like most about school?",

            in_array($topicSlug, ['greetings']) || str_contains($topic, 'greeting')
                => "Let's practice greetings! 👋 In English, you can say 'Hello!', 'Hi there!', or 'Good morning!' How do you usually greet people?",

            in_array($topicSlug, ['shopping']) || str_contains($topic, 'shop') || str_contains($msg, 'buy') || str_contains($msg, 'shop') || str_contains($msg, 'store')
                => "Shopping vocabulary is very practical! 🛍️ You can say 'How much does this cost?' or 'I'm looking for...' What do you like to shop for?",

            in_array($topicSlug, ['health']) || str_contains($topic, 'health') || str_contains($msg, 'doctor') || str_contains($msg, 'sick') || str_contains($msg, 'hospital')
                => "Health is very important! 🏥 You can say 'I don't feel well' or 'I need to see a doctor.' Do you try to stay healthy?",

            default
                => "That's interesting! 😊 Could you tell me more? I'd love to help you practice your English. What would you like to say next?",
        };

        // [reply, promptTokens, completionTokens, totalTokens, model]
        return [$reply, 0, 0, 0, 'fallback'];
    }
}
