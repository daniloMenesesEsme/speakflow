<?php

namespace App\Services;

use App\Models\ConversationSession;
use App\Models\ConversationMessage;
use App\Models\Dialogue;
use App\Models\DialogueLine;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * ConversationEngine — Motor de conversação do SpeakFlow.
 *
 * Funciona 100% offline com diálogos pré-cadastrados no banco.
 *
 * Responsabilidades:
 *   - Iniciar conversa por tema (slug)
 *   - Retomar conversa pausada
 *   - Entregar a próxima linha ao app mobile
 *   - Validar resposta do usuário com múltiplos algoritmos
 *   - Registrar histórico completo de mensagens
 *   - Finalizar e pontuar a sessão
 */
class ConversationEngine
{
    // ─── Thresholds de similaridade ─────────────────────────────────────────
    private const THRESHOLD_CORRECT    = 0.72;  // >= 72% → considerado correto
    private const THRESHOLD_CLOSE      = 0.50;  // 50–71% → "quase lá"
    private const KEYWORD_BONUS        = 0.10;  // bônus quando todas as palavras-chave estão presentes

    // ─── XP por qualidade de sessão ─────────────────────────────────────────
    private const XP_BASE_PER_TURN      = 5;
    private const XP_BONUS_PERFECT      = 20;   // 100% de acerto na sessão
    private const XP_BONUS_HIGH         = 10;   // >= 80%
    private const XP_BONUS_PER_TURN_CORRECT = 3;

    // ══════════════════════════════════════════════════════════════════════════
    //  1. INICIAR CONVERSA
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Inicia uma nova conversa para o usuário no tema solicitado.
     * Seleciona o diálogo mais adequado ao nível do usuário.
     *
     * @param  string  $topic  Slug do tema: restaurant, airport, hotel, etc.
     */
    public function startConversation(string $topic, User $user): array
    {
        $topic = Str::slug($topic);

        $dialogue = $this->selectDialogueForTopic($topic, $user);

        if (!$dialogue) {
            return [
                'success' => false,
                'error'   => "Nenhum diálogo disponível para o tema '{$topic}'.",
                'available_topics' => $this->getAvailableTopics($user->target_language),
            ];
        }

        $lines = $dialogue->lines()->orderBy('order')->get();

        // Abandon qualquer sessão ativa anterior no mesmo tema
        ConversationSession::forUser($user->id)
            ->byTopic($topic)
            ->active()
            ->update(['status' => 'abandoned', 'last_activity_at' => now()]);

        $session = ConversationSession::create([
            'user_id'          => $user->id,
            'dialogue_id'      => $dialogue->id,
            'topic_slug'       => $topic,
            'status'           => 'active',
            'current_line_order' => 0,
            'total_lines'      => $lines->count(),
            'user_turns_total' => $lines->where('is_user_turn', true)->count(),
            'started_at'       => now(),
            'last_activity_at' => now(),
        ]);

        // Registra a primeira linha do app como primeira mensagem
        $firstLine = $lines->first();
        if ($firstLine && !$firstLine->is_user_turn) {
            $this->registerConversationMessage(
                session: $session,
                line:    $firstLine,
                sender:  'app',
                message: $firstLine->text,
            );
            $session->increment('current_line_order');
            $session->touchActivity();
        }

        return [
            'success'     => true,
            'session'     => $this->formatSession($session),
            'dialogue'    => $this->formatDialogueInfo($dialogue),
            'first_line'  => $firstLine ? $this->formatLine($firstLine) : null,
            'next_line'   => $this->resolveNextLine($session, $lines),
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  2. PRÓXIMA LINHA DO DIÁLOGO
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Retorna a próxima linha a ser exibida/respondida na sessão.
     * Também serve para retomada após pausa (offline resume).
     */
    public function getNextDialogueLine(int $conversationSessionId, int $userId): array
    {
        $session = ConversationSession::forUser($userId)
            ->with('dialogue.lines')
            ->find($conversationSessionId);

        if (!$session) {
            return ['success' => false, 'error' => 'Sessão não encontrada.'];
        }

        if ($session->isCompleted()) {
            return [
                'success'   => false,
                'completed' => true,
                'summary'   => $this->buildSessionSummary($session),
            ];
        }

        $lines = $session->dialogue->lines()->orderBy('order')->get();

        $nextLine = $lines->firstWhere('order', '>', $session->current_line_order - 1);

        if (!$nextLine) {
            return [
                'success'   => false,
                'completed' => true,
                'summary'   => $this->buildSessionSummary($session),
            ];
        }

        return [
            'success'           => true,
            'session_id'        => $session->id,
            'current_line_order'=> $session->current_line_order,
            'total_lines'       => $session->total_lines,
            'progress_pct'      => $session->progress_percentage,
            'line'              => $this->formatLine($nextLine),
            'is_last'           => $lines->last()?->id === $nextLine->id,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  3. VALIDAR RESPOSTA DO USUÁRIO
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Valida a entrada do usuário contra a resposta esperada.
     * Usa múltiplos algoritmos combinados para máxima tolerância.
     *
     * @return array{
     *   is_correct: bool,
     *   similarity: float,
     *   quality_score: int,
     *   grade: string,
     *   feedback: string,
     *   expected_answer: string,
     *   diff: array,
     *   hints: array,
     * }
     */
    public function validateUserResponse(string $userInput, string $expectedAnswer): array
    {
        $normalizedUser     = $this->normalizeText($userInput);
        $normalizedExpected = $this->normalizeText($expectedAnswer);

        // Algoritmos de similaridade
        $exactMatch      = $normalizedUser === $normalizedExpected;
        $levenshteinScore= $this->levenshteinSimilarity($normalizedUser, $normalizedExpected);
        $similarTextScore= $this->similarTextScore($normalizedUser, $normalizedExpected);
        $keywordScore    = $this->keywordMatchScore($normalizedUser, $normalizedExpected);

        // Score combinado ponderado
        $combinedScore = $exactMatch
            ? 1.0
            : ($levenshteinScore * 0.45 + $similarTextScore * 0.40 + $keywordScore * 0.15);

        // Aplica bônus de keywords se todas estiverem presentes
        if (!$exactMatch && $keywordScore >= 1.0) {
            $combinedScore = min(1.0, $combinedScore + self::KEYWORD_BONUS);
        }

        $combinedScore = round($combinedScore, 4);
        $isCorrect     = $combinedScore >= self::THRESHOLD_CORRECT;
        $qualityScore  = (int) round($combinedScore * 100);
        $grade         = $this->scoreToGrade($qualityScore);

        return [
            'is_correct'      => $isCorrect,
            'similarity'      => $combinedScore,
            'quality_score'   => $qualityScore,
            'grade'           => $grade,
            'status'          => $this->resolveStatus($combinedScore),
            'feedback'        => $this->generateFeedback($isCorrect, $qualityScore, $userInput, $expectedAnswer),
            'expected_answer' => $expectedAnswer,
            'user_answer'     => $userInput,
            'diff'            => $this->buildDiff($normalizedUser, $normalizedExpected),
            'scores_detail'   => [
                'levenshtein'  => round($levenshteinScore, 3),
                'similar_text' => round($similarTextScore, 3),
                'keyword'      => round($keywordScore, 3),
                'combined'     => $combinedScore,
            ],
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  4. REGISTRAR MENSAGEM NO HISTÓRICO
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Persiste uma mensagem no histórico da sessão de conversa.
     * Chamado tanto para mensagens do app quanto do usuário.
     */
    public function registerConversationMessage(
        ConversationSession $session,
        ?DialogueLine $line,
        string $sender,
        string $message,
        ?string $userAnswer     = null,
        float $similarityScore  = 0.0,
        int $qualityScore       = 0,
        bool $isCorrect         = false,
        ?array $metadata        = null,
    ): ConversationMessage {
        $msg = ConversationMessage::create([
            'conversation_session_id' => $session->id,
            'dialogue_line_id'        => $line?->id,
            'sender'                  => $sender,
            'message'                 => $message,
            'expected_answer'         => $line?->expected_answer,
            'user_answer'             => $userAnswer,
            'similarity_score'        => $similarityScore,
            'quality_score'           => $qualityScore,
            'is_correct'              => $isCorrect,
            'is_user_turn'            => $line?->is_user_turn ?? false,
            'line_order'              => $line?->order ?? $session->current_line_order,
            'metadata'                => $metadata,
            'sent_at'                 => now(),
        ]);

        $session->increment('messages_count');

        return $msg;
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  5. PROCESSAR RESPOSTA E AVANÇAR NA CONVERSA
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Recebe a resposta do usuário, valida, registra e avança para a próxima linha.
     * Este é o método principal chamado pelo app a cada turno do usuário.
     */
    public function processUserTurn(
        ConversationSession $session,
        DialogueLine $line,
        string $userInput,
        ?array $metadata = null,
    ): array {
        if (!$session->isActive()) {
            return ['success' => false, 'error' => 'Esta sessão não está mais ativa.'];
        }

        // Valida resposta
        $validation = $this->validateUserResponse($userInput, $line->expected_answer ?? '');

        // Registra mensagem do usuário
        $this->registerConversationMessage(
            session:         $session,
            line:            $line,
            sender:          'user',
            message:         $userInput,
            userAnswer:      $userInput,
            similarityScore: $validation['similarity'],
            qualityScore:    $validation['quality_score'],
            isCorrect:       $validation['is_correct'],
            metadata:        $metadata,
        );

        // Atualiza contadores da sessão
        if ($validation['is_correct']) {
            $session->increment('user_turns_correct');
        }

        $session->increment('total_score', $validation['quality_score']);
        $session->current_line_order = $line->order + 1;
        $session->touchActivity();

        // Busca próxima linha
        $allLines = $session->dialogue->lines()->orderBy('order')->get();
        $nextLine = $allLines->firstWhere('order', '>', $line->order);

        // Se próxima linha é do app, registra automaticamente
        if ($nextLine && !$nextLine->is_user_turn) {
            $this->registerConversationMessage(
                session:  $session,
                line:     $nextLine,
                sender:   'app',
                message:  $nextLine->text,
            );
            $session->current_line_order = $nextLine->order + 1;
            $session->save();

            // Pula para a linha seguinte ao app
            $nextLine = $allLines->firstWhere('order', '>', $nextLine->order);
        } else {
            $session->save();
        }

        $isLast = $nextLine === null;

        if ($isLast) {
            $summary = $this->finalizeSession($session);

            return [
                'success'    => true,
                'validation' => $validation,
                'completed'  => true,
                'summary'    => $summary,
            ];
        }

        return [
            'success'    => true,
            'validation' => $validation,
            'completed'  => false,
            'next_line'  => $this->formatLine($nextLine),
            'session'    => [
                'progress_pct'  => $session->progress_percentage,
                'messages_count'=> $session->messages_count,
            ],
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  6. GERAR RESPOSTA DO APP PARA LINHAS NÃO INTERATIVAS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Gera a resposta do app para uma linha que não exige input do usuário.
     * Avança automaticamente linhas consecutivas do app.
     */
    public function generateAppResponse(
        ConversationSession $session,
        DialogueLine $line,
    ): array {
        $appLines = [];
        $allLines = $session->dialogue->lines()->orderBy('order')->get();
        $current  = $line;

        // Processa todas as linhas consecutivas do app
        while ($current && !$current->is_user_turn) {
            $this->registerConversationMessage(
                session: $session,
                line:    $current,
                sender:  'app',
                message: $current->text,
            );

            $appLines[] = $this->formatLine($current);
            $session->current_line_order = $current->order + 1;
            $current = $allLines->firstWhere('order', '>', $current->order);
        }

        $session->touchActivity();

        return [
            'app_lines' => $appLines,
            'next_user_line' => $current ? $this->formatLine($current) : null,
            'is_completed'   => $current === null,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  7. HISTÓRICO DA CONVERSA
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Retorna o histórico completo de mensagens de uma sessão.
     * Permite retomada offline do estado exato da conversa.
     */
    public function getConversationHistory(int $sessionId, int $userId): array
    {
        $session = ConversationSession::forUser($userId)
            ->with(['messages' => fn ($q) => $q->orderBy('line_order'), 'dialogue'])
            ->find($sessionId);

        if (!$session) {
            return ['success' => false, 'error' => 'Sessão não encontrada.'];
        }

        $messages = $session->messages->map(fn (ConversationMessage $msg) => [
            'id'             => $msg->id,
            'sender'         => $msg->sender,
            'message'        => $msg->message,
            'expected_answer'=> $msg->expected_answer,
            'user_answer'    => $msg->user_answer,
            'is_user_turn'   => $msg->is_user_turn,
            'is_correct'     => $msg->is_correct,
            'quality_score'  => $msg->quality_score,
            'line_order'     => $msg->line_order,
            'sent_at'        => $msg->sent_at->toISOString(),
        ]);

        return [
            'success'  => true,
            'session'  => $this->formatSession($session),
            'messages' => $messages,
            'stats'    => [
                'accuracy'         => $session->accuracy_percentage,
                'average_score'    => $session->average_score,
                'grade'            => $session->grade,
                'duration_minutes' => $session->duration_minutes,
            ],
        ];
    }

    /**
     * Retorna todas as sessões de um tema específico do usuário.
     */
    public function getUserTopicHistory(User $user, string $topicSlug): array
    {
        $sessions = ConversationSession::forUser($user->id)
            ->byTopic($topicSlug)
            ->with('dialogue:id,topic,level')
            ->orderByDesc('started_at')
            ->get()
            ->map(fn (ConversationSession $s) => [
                'id'            => $s->id,
                'status'        => $s->status,
                'grade'         => $s->isCompleted() ? $s->grade : null,
                'accuracy'      => $s->accuracy_percentage,
                'xp_earned'     => $s->xp_earned,
                'duration_min'  => $s->duration_minutes,
                'started_at'    => $s->started_at->toISOString(),
                'completed_at'  => $s->completed_at?->toISOString(),
            ]);

        return [
            'topic'     => $topicSlug,
            'label'     => Dialogue::topicLabel($topicSlug),
            'sessions'  => $sessions,
            'best_grade'=> $sessions->where('status', 'completed')->max('grade'),
            'attempts'  => $sessions->count(),
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  8. TEMAS E DIÁLOGOS DISPONÍVEIS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Lista os temas disponíveis para o idioma/nível do usuário.
     * Funciona offline: usa apenas dados pré-cadastrados.
     */
    public function getAvailableTopics(string $languageCode, ?string $level = null): array
    {
        $query = Dialogue::forLanguage($languageCode)->active();

        if ($level) {
            $query->byLevel($level);
        }

        return $query
            ->selectRaw('slug, topic_category, COUNT(*) as dialogue_count, MIN(level) as min_level')
            ->whereNotNull('slug')
            ->groupBy('slug', 'topic_category')
            ->get()
            ->map(fn ($row) => [
                'slug'           => $row->slug,
                'label'          => Dialogue::topicLabel($row->slug),
                'category'       => $row->topic_category,
                'dialogue_count' => $row->dialogue_count,
                'min_level'      => $row->min_level,
            ])
            ->toArray();
    }

    /**
     * Retorna diálogos de um tema específico, por nível do usuário.
     */
    public function getDialoguesByTopic(
        string $topicSlug,
        User $user,
        int $limit = 5
    ): Collection {
        return Dialogue::forUser($user)
            ->byTopic($topicSlug)
            ->with(['lines' => fn ($q) => $q->orderBy('order')->limit(4)])
            ->limit($limit)
            ->get();
    }

    /**
     * Busca um diálogo aleatório compatível com o nível do usuário.
     */
    public function getRandomDialogue(User $user, ?string $topicSlug = null): ?Dialogue
    {
        $query = Dialogue::forUser($user);

        if ($topicSlug) {
            $query->byTopic($topicSlug);
        }

        return $query->inRandomOrder()->first();
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  9. FINALIZAÇÃO
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Finaliza e pontua a sessão. Chamado automaticamente na última linha
     * ou manualmente pelo usuário via endpoint dedicado.
     */
    public function finalizeSession(ConversationSession $session): array
    {
        if ($session->isCompleted()) {
            return $this->buildSessionSummary($session);
        }

        $xpEarned = $this->calculateSessionXp($session);
        $session->markCompleted($xpEarned);

        // Incrementa XP do usuário
        $session->user()->increment('total_xp', $xpEarned);

        return $this->buildSessionSummary($session->fresh());
    }

    /**
     * Abandona uma sessão ativa.
     */
    public function abandonSession(ConversationSession $session): void
    {
        if ($session->isActive()) {
            $session->status           = 'abandoned';
            $session->last_activity_at = now();
            $session->save();
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  PRIVADOS — ALGORITMOS DE SIMILARIDADE
    // ══════════════════════════════════════════════════════════════════════════

    private function levenshteinSimilarity(string $a, string $b): float
    {
        if ($a === $b) {
            return 1.0;
        }

        $maxLen = max(mb_strlen($a), mb_strlen($b));

        if ($maxLen === 0) {
            return 1.0;
        }

        $distance = levenshtein($a, $b);

        return max(0.0, 1 - ($distance / $maxLen));
    }

    private function similarTextScore(string $a, string $b): float
    {
        similar_text($a, $b, $percent);

        return $percent / 100;
    }

    /**
     * Verifica quantas palavras-chave da resposta esperada aparecem na entrada.
     * Ignora stop-words de baixo valor semântico.
     */
    private function keywordMatchScore(string $userInput, string $expected): float
    {
        $stopWords = ['a', 'an', 'the', 'i', 'you', 'to', 'of', 'in', 'is', 'it', 'and', 'or'];

        $keywords = array_filter(
            explode(' ', $expected),
            fn ($w) => strlen($w) > 2 && !in_array($w, $stopWords)
        );

        if (empty($keywords)) {
            return $this->levenshteinSimilarity($userInput, $expected);
        }

        $matched = 0;
        foreach ($keywords as $keyword) {
            if (str_contains($userInput, $keyword) || str_contains($userInput, rtrim($keyword, 's'))) {
                $matched++;
            }
        }

        return $matched / count($keywords);
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  PRIVADOS — HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    private function normalizeText(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace("/['']/", "'", $text);
        $text = preg_replace('/[^\w\s\']/u', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    private function selectDialogueForTopic(string $topicSlug, User $user): ?Dialogue
    {
        // Tenta nível exato primeiro
        $dialogue = Dialogue::forUser($user)->byTopic($topicSlug)->inRandomOrder()->first();

        if (!$dialogue) {
            // Fallback: qualquer nível para o tema
            $dialogue = Dialogue::forLanguage($user->target_language)
                ->byTopic($topicSlug)
                ->active()
                ->inRandomOrder()
                ->first();
        }

        return $dialogue;
    }

    private function resolveNextLine(ConversationSession $session, Collection $lines): ?array
    {
        $nextLine = $lines->firstWhere('order', '>=', $session->current_line_order);

        return $nextLine ? $this->formatLine($nextLine) : null;
    }

    private function generateFeedback(
        bool $isCorrect,
        int $score,
        string $userInput,
        string $expectedAnswer
    ): string {
        if ($isCorrect) {
            return match (true) {
                $score >= 97 => 'Perfeito! Exatamente correto!',
                $score >= 90 => 'Excelente! Muito próximo do ideal.',
                $score >= 80 => 'Muito bem! Pequenas diferenças, mas ótima resposta.',
                default      => 'Correto! Continue assim.',
            };
        }

        $userWords     = explode(' ', $this->normalizeText($userInput));
        $expectedWords = explode(' ', $this->normalizeText($expectedAnswer));
        $missing       = array_diff($expectedWords, $userWords);

        if (count($missing) <= 2 && count($missing) > 0) {
            $missingStr = implode(', ', array_slice($missing, 0, 2));
            return "Quase lá! Você esqueceu: \"{$missingStr}\".";
        }

        return match (true) {
            $score >= 50 => 'Boa tentativa! Confira a resposta esperada e tente novamente.',
            $score >= 30 => 'Continue praticando! Leia a frase com atenção.',
            default      => 'Não desista! Tente ouvir o áudio e repetir.',
        };
    }

    private function buildDiff(string $userInput, string $expected): array
    {
        $userWords     = explode(' ', $userInput);
        $expectedWords = explode(' ', $expected);
        $diff          = [];

        $maxLen = max(count($userWords), count($expectedWords));

        for ($i = 0; $i < $maxLen; $i++) {
            $user = $userWords[$i] ?? null;
            $exp  = $expectedWords[$i] ?? null;

            $diff[] = [
                'expected' => $exp,
                'given'    => $user,
                'match'    => $user !== null && $exp !== null && $user === $exp,
            ];
        }

        return $diff;
    }

    private function resolveStatus(float $score): string
    {
        return match (true) {
            $score >= self::THRESHOLD_CORRECT => 'correct',
            $score >= self::THRESHOLD_CLOSE   => 'close',
            default                           => 'incorrect',
        };
    }

    private function scoreToGrade(int $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default      => 'F',
        };
    }

    private function calculateSessionXp(ConversationSession $session): int
    {
        $base    = $session->user_turns_total * self::XP_BASE_PER_TURN;
        $correct = $session->user_turns_correct * self::XP_BONUS_PER_TURN_CORRECT;

        $accuracy = $session->user_turns_total > 0
            ? $session->user_turns_correct / $session->user_turns_total
            : 0;

        $bonus = match (true) {
            $accuracy >= 1.0 => self::XP_BONUS_PERFECT,
            $accuracy >= 0.8 => self::XP_BONUS_HIGH,
            default          => 0,
        };

        return $base + $correct + $bonus;
    }

    private function buildSessionSummary(ConversationSession $session): array
    {
        return [
            'session_id'       => $session->id,
            'topic'            => $session->topic_slug,
            'status'           => $session->status,
            'completed'        => $session->isCompleted(),
            'accuracy'         => $session->accuracy_percentage,
            'average_score'    => $session->average_score,
            'grade'            => $session->grade,
            'xp_earned'        => $session->xp_earned,
            'user_turns_total' => $session->user_turns_total,
            'user_turns_correct'=> $session->user_turns_correct,
            'duration_minutes' => $session->duration_minutes,
            'message'          => $this->completionMessage($session->average_score),
            'started_at'       => $session->started_at->toISOString(),
            'completed_at'     => $session->completed_at?->toISOString(),
        ];
    }

    private function completionMessage(float $averageScore): string
    {
        return match (true) {
            $averageScore >= 90 => 'Incrível! Você dominou esse diálogo!',
            $averageScore >= 80 => 'Ótimo trabalho! Continue assim.',
            $averageScore >= 70 => 'Bom progresso! Pratique mais uma vez para fixar.',
            $averageScore >= 60 => 'Você está melhorando! Tente novamente para melhorar a nota.',
            default             => 'Não desista! Revise o diálogo e pratique novamente.',
        };
    }

    private function formatSession(ConversationSession $session): array
    {
        return [
            'id'                  => $session->id,
            'topic_slug'          => $session->topic_slug,
            'status'              => $session->status,
            'current_line_order'  => $session->current_line_order,
            'total_lines'         => $session->total_lines,
            'progress_percentage' => $session->progress_percentage,
            'messages_count'      => $session->messages_count,
            'started_at'          => $session->started_at->toISOString(),
        ];
    }

    private function formatDialogueInfo(Dialogue $dialogue): array
    {
        return [
            'id'          => $dialogue->id,
            'topic'       => $dialogue->topic,
            'slug'        => $dialogue->slug,
            'level'       => $dialogue->level,
            'context'     => $dialogue->context,
            'description' => $dialogue->description,
            'total_lines' => $dialogue->lines()->count(),
            'estimated_minutes' => $dialogue->estimated_minutes,
        ];
    }

    private function formatLine(DialogueLine $line): array
    {
        return [
            'id'              => $line->id,
            'order'           => $line->order,
            'speaker'         => $line->speaker,
            'text'            => $line->text,
            'translation'     => $line->translation,
            'audio_path'      => $line->audio_path,
            'is_user_turn'    => $line->is_user_turn,
            'hints'           => $line->is_user_turn ? ($line->hints ?? []) : [],
        ];
    }
}
