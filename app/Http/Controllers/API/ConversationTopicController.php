<?php

namespace App\Http\Controllers\API;

use App\Models\ConversationTopic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationTopicController extends BaseController
{
    /**
     * GET /api/v1/conversation-topics
     *
     * Lista todos os tópicos ativos, opcionalmente filtrados pelo nível do usuário.
     * Aceita query param ?level=A1 para retornar apenas tópicos acessíveis ao nível.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ConversationTopic::active()->orderBy('level')->orderBy('title');

        // Filtro opcional por nível (retorna tópicos até o nível informado)
        if ($request->filled('level')) {
            $request->validate(['level' => 'in:A1,A2,B1,B2,C1,C2']);
            $query->forLevel($request->level);
        }

        $topics = $query->get()->map(fn ($t) => $this->formatTopic($t));

        return $this->success([
            'total'  => $topics->count(),
            'topics' => $topics,
        ], 'Tópicos de conversa disponíveis.');
    }

    /**
     * GET /api/v1/conversation-topics/{topic}
     *
     * Detalhe de um tópico com suas conversas recentes do usuário.
     */
    public function show(ConversationTopic $conversationTopic): JsonResponse
    {
        if (! $conversationTopic->active) {
            return $this->error('Tópico não disponível.', 404);
        }

        $user = auth()->user();

        $recentConversations = $conversationTopic->conversations()
            ->where('user_id', $user->id)
            ->orderByDesc('last_message_at')
            ->limit(5)
            ->get()
            ->map(fn ($c) => [
                'id'              => $c->id,
                'messages_count'  => $c->messages_count,
                'last_message_at' => $c->last_message_at?->toISOString(),
                'created_at'      => $c->created_at->toISOString(),
            ]);

        return $this->success([
            'topic'                => $this->formatTopic($conversationTopic),
            'my_conversations'     => $recentConversations,
            'my_conversations_count' => $recentConversations->count(),
        ], 'Detalhes do tópico.');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function formatTopic(ConversationTopic $topic): array
    {
        return [
            'id'          => $topic->id,
            'title'       => $topic->title,
            'slug'        => $topic->slug,
            'description' => $topic->description,
            'level'       => $topic->level,
            'icon'        => $topic->icon,
        ];
    }
}
