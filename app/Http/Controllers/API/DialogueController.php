<?php

namespace App\Http\Controllers\API;

use App\Models\Dialogue;
use App\Models\DialogueLine;
use App\Models\ConversationSession;
use App\Services\ConversationEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DialogueController extends BaseController
{
    public function __construct(private ConversationEngine $engine) {}

    // ══════════════════════════════════════════════════════════════════════════
    //  TEMAS E DIÁLOGOS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * GET /api/v1/dialogues/topics
     * Lista os temas disponíveis para o idioma/nível do usuário.
     */
    public function topics(Request $request): JsonResponse
    {
        $user   = auth()->user();
        $topics = $this->engine->getAvailableTopics(
            $user->target_language,
            $request->get('level', $user->level)
        );

        return $this->success([
            'topics'    => $topics,
            'all_topics'=> Dialogue::TOPICS,
        ], 'Temas disponíveis.');
    }

    /**
     * GET /api/v1/dialogues
     * Lista diálogos com filtros opcionais de nível, tema e idioma.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        $query = Dialogue::forLanguage($request->get('language_code', $user->target_language))
            ->active();

        if ($request->filled('level')) {
            $query->byLevel($request->level);
        } else {
            $query->byLevel($user->level);
        }

        if ($request->filled('topic')) {
            $query->byTopic($request->topic);
        }

        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }

        $dialogues = $query->withCount('lines')
            ->paginate($request->get('per_page', 10));

        return $this->paginated($dialogues, 'Diálogos listados.');
    }

    /**
     * GET /api/v1/dialogues/{dialogue}
     * Detalhe de um diálogo com todas as suas linhas.
     */
    public function show(Dialogue $dialogue): JsonResponse
    {
        $dialogue->load(['language', 'lines' => fn ($q) => $q->orderBy('order')]);

        return $this->success([
            'id'                => $dialogue->id,
            'topic'             => $dialogue->topic,
            'slug'              => $dialogue->slug,
            'topic_label'       => $dialogue->topic_label,
            'level'             => $dialogue->level,
            'description'       => $dialogue->description,
            'context'           => $dialogue->context,
            'estimated_minutes' => $dialogue->estimated_minutes,
            'language'          => [
                'id'   => $dialogue->language->id,
                'name' => $dialogue->language->name,
                'code' => $dialogue->language->code,
            ],
            'lines' => $dialogue->lines->map(fn ($line) => [
                'id'           => $line->id,
                'order'        => $line->order,
                'speaker'      => $line->speaker,
                'text'         => $line->text,
                'translation'  => $line->translation,
                'audio_path'   => $line->audio_path,
                'is_user_turn' => $line->is_user_turn,
                'hints'        => $line->is_user_turn ? ($line->hints ?? []) : [],
            ]),
            'total_lines' => $dialogue->lines->count(),
        ]);
    }

    /**
     * GET /api/v1/dialogues/random
     * Retorna um diálogo aleatório para o nível do usuário.
     */
    public function random(Request $request): JsonResponse
    {
        $user     = auth()->user();
        $dialogue = $this->engine->getRandomDialogue($user, $request->get('topic'));

        if (!$dialogue) {
            return $this->notFound('Nenhum diálogo disponível para seu perfil.');
        }

        return $this->success([
            'id'          => $dialogue->id,
            'topic'       => $dialogue->topic,
            'slug'        => $dialogue->slug,
            'topic_label' => $dialogue->topic_label,
            'level'       => $dialogue->level,
            'total_lines' => $dialogue->lines()->count(),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  GERENCIAMENTO DE SESSÃO
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * POST /api/v1/conversations/start
     * Inicia uma nova conversa em um tema específico.
     *
     * Body: { "topic": "restaurant" }
     */
    public function startConversation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'topic' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->error('Dados inválidos.', $validator->errors(), 422);
        }

        $result = $this->engine->startConversation($request->topic, auth()->user());

        if (!$result['success']) {
            return $this->error($result['error'], [
                'available_topics' => $result['available_topics'] ?? [],
            ], 404);
        }

        return $this->created($result, 'Conversa iniciada!');
    }

    /**
     * GET /api/v1/conversations/{session}/next-line
     * Retorna a próxima linha da sessão (suporte a retomada offline).
     */
    public function nextLine(int $session): JsonResponse
    {
        $result = $this->engine->getNextDialogueLine($session, auth()->id());

        if (!$result['success']) {
            if ($result['completed'] ?? false) {
                return $this->success($result['summary'], 'Conversa concluída.');
            }

            return $this->notFound($result['error'] ?? 'Não foi possível obter a próxima linha.');
        }

        return $this->success($result);
    }

    /**
     * POST /api/v1/conversations/{session}/respond
     * Processa a resposta do usuário e avança a conversa.
     *
     * Body: { "line_id": 42, "answer": "I'd like a coffee, please." }
     */
    public function respond(Request $request, ConversationSession $session): JsonResponse
    {
        if ($session->user_id !== auth()->id()) {
            return $this->unauthorized('Esta sessão não pertence a você.');
        }

        $validator = Validator::make($request->all(), [
            'line_id' => 'required|integer|exists:dialogue_lines,id',
            'answer'  => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->error('Dados inválidos.', $validator->errors(), 422);
        }

        $line   = DialogueLine::findOrFail($request->line_id);
        $result = $this->engine->processUserTurn($session, $line, $request->answer);

        if (!$result['success']) {
            return $this->error($result['error'], null, 422);
        }

        return $this->success($result, $result['completed'] ? 'Conversa concluída!' : 'Resposta processada.');
    }

    /**
     * POST /api/v1/conversations/{session}/validate
     * Valida uma resposta sem avançar a conversa (modo prática).
     *
     * Body: { "answer": "...", "expected": "..." }
     */
    public function validate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'answer'   => 'required|string|max:1000',
            'expected' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->error('Dados inválidos.', $validator->errors(), 422);
        }

        $result = $this->engine->validateUserResponse($request->answer, $request->expected);

        return $this->success($result, 'Validação concluída.');
    }

    /**
     * POST /api/v1/conversations/{session}/complete
     * Finaliza manualmente uma sessão ativa.
     */
    public function complete(ConversationSession $session): JsonResponse
    {
        if ($session->user_id !== auth()->id()) {
            return $this->unauthorized('Esta sessão não pertence a você.');
        }

        $summary = $this->engine->finalizeSession($session);

        return $this->success($summary, 'Conversa finalizada!');
    }

    /**
     * POST /api/v1/conversations/{session}/abandon
     * Abandona uma sessão ativa.
     */
    public function abandon(ConversationSession $session): JsonResponse
    {
        if ($session->user_id !== auth()->id()) {
            return $this->unauthorized('Esta sessão não pertence a você.');
        }

        $this->engine->abandonSession($session);

        return $this->success(null, 'Sessão encerrada.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  HISTÓRICO
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * GET /api/v1/conversations
     * Lista todas as sessões do usuário.
     */
    public function mySessions(Request $request): JsonResponse
    {
        $sessions = ConversationSession::forUser(auth()->id())
            ->with('dialogue:id,topic,slug,level')
            ->orderByDesc('started_at')
            ->paginate($request->get('per_page', 15));

        return $this->paginated($sessions, 'Sessões listadas.');
    }

    /**
     * GET /api/v1/conversations/{session}/history
     * Histórico completo de mensagens de uma sessão.
     */
    public function history(int $session): JsonResponse
    {
        $result = $this->engine->getConversationHistory($session, auth()->id());

        if (!$result['success']) {
            return $this->notFound($result['error']);
        }

        return $this->success($result);
    }

    /**
     * GET /api/v1/conversations/topics/{topic}/history
     * Histórico de tentativas em um tema específico.
     */
    public function topicHistory(string $topic): JsonResponse
    {
        $result = $this->engine->getUserTopicHistory(auth()->user(), $topic);

        return $this->success($result);
    }
}
