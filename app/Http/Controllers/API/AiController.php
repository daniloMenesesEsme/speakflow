<?php

namespace App\Http\Controllers\API;

use App\Models\AiConversation;
use App\Services\AiTutorService;
use App\Services\DailyMissionService;
use App\Services\VoiceTranscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends BaseController
{
    public function __construct(
        private AiTutorService            $tutor,
        private VoiceTranscriptionService $voice,
        private DailyMissionService       $missions,
    ) {
    }

    /**
     * POST /api/v1/ai/chat
     *
     * Envia uma mensagem ao tutor virtual e recebe a resposta.
     * Inicia nova conversa se `conversation_id` não for fornecido.
     *
     * Body:
     * {
     *   "message":         "Hello, how are you?",   // obrigatório
     *   "conversation_id": 5,                         // opcional
     *   "topic":           "restaurant"              // opcional, só na 1ª mensagem
     * }
     */
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message'         => 'required|string|min:1|max:2000',
            'conversation_id' => 'nullable|integer|exists:ai_conversations,id',
            'topic'           => 'nullable|string|max:100',
            'topic_id'        => 'nullable|integer|exists:conversation_topics,id',
        ]);

        $user   = auth()->user();
        $result = $this->tutor->chat(
            user:            $user,
            userMessage:     $validated['message'],
            conversationId:  $validated['conversation_id'] ?? null,
            topic:           $validated['topic'] ?? null,
            topicId:         $validated['topic_id'] ?? null,
        );

        // Missão diária: conversa com tutor
        $this->missions->updateProgress(auth()->user(), 'conversation');

        return $this->success([
            'reply'           => $result['reply'],
            'conversation_id' => $result['conversation_id'],
            'model'           => $result['model'],
            'topic'           => $result['topic'],
            'usage'           => $result['usage'],
            'correction'      => $result['correction'],
        ], 'Resposta do tutor.');
    }

    /**
     * POST /api/v1/ai/voice
     *
     * Recebe um arquivo de áudio, transcreve com Whisper e envia o texto
     * ao AiTutorService para gerar resposta do tutor.
     *
     * Multipart form-data:
     *   audio_file      — arquivo de áudio (mp3, wav, webm, ogg, m4a, flac)
     *   conversation_id — opcional: continuar conversa existente
     *   topic_id        — opcional: associar ao tópico (somente na 1ª mensagem)
     */
    public function voice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'audio_file'      => 'required|file|mimes:mp3,wav,webm,ogg,m4a,flac,mp4,mpeg,mpga,oga|max:25600',
            'conversation_id' => 'nullable|integer|exists:ai_conversations,id',
            'topic_id'        => 'nullable|integer|exists:conversation_topics,id',
        ]);

        $user         = auth()->user();
        $audioFile    = $validated['audio_file'];
        $convId       = $validated['conversation_id'] ?? null;

        // ── Buscar conversa existente se fornecida ────────────────────────────
        $conversation = $convId
            ? AiConversation::where('id', $convId)->where('user_id', $user->id)->firstOrFail()
            : null;

        // ── 1. Transcrever áudio ──────────────────────────────────────────────
        $transcriptionResult = $this->voice->transcribe(
            user:         $user,
            audioFile:    $audioFile,
            conversation: $conversation,
        );

        $transcription = $transcriptionResult['transcription'];

        // Áudio inaudível ou vazio — retorna sem chamar o chat
        if (empty(trim($transcription)) || $transcription === '[inaudível]') {
            return $this->success([
                'transcription'     => $transcription,
                'reply'             => null,
                'conversation_id'   => $conversation?->id,
                'voice_message_id'  => $transcriptionResult['voice_message_id'],
                'driver'            => $transcriptionResult['driver'],
                'detected_language' => $transcriptionResult['detected_language'],
                'processing_time_ms'=> $transcriptionResult['processing_time_ms'],
            ], 'Áudio recebido mas não foi possível transcrever.');
        }

        // ── 2. Gerar resposta do tutor com o texto transcrito ─────────────────
        $chatResult = $this->tutor->chat(
            user:           $user,
            userMessage:    $transcription,
            conversationId: $conversation?->id,
            topicId:        $validated['topic_id'] ?? null,
        );

        // Missão diária: mensagem de voz
        $this->missions->updateProgress($user, 'voice_message');

        return $this->success([
            'transcription'     => $transcription,
            'reply'             => $chatResult['reply'],
            'conversation_id'   => $chatResult['conversation_id'],
            'topic'             => $chatResult['topic'],
            'correction'        => $chatResult['correction'],
            'voice_message_id'  => $transcriptionResult['voice_message_id'],
            'driver'            => $transcriptionResult['driver'],
            'detected_language' => $transcriptionResult['detected_language'],
            'processing_time_ms'=> $transcriptionResult['processing_time_ms'],
            'usage'             => $chatResult['usage'],
        ], 'Mensagem de voz processada com sucesso.');
    }

    /**
     * GET /api/v1/ai/voice
     *
     * Retorna o histórico de mensagens de voz do usuário, paginado.
     */
    public function voiceHistory(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 50);
        $data    = $this->voice->getHistory(auth()->user(), $perPage);

        return $this->success($data, 'Histórico de mensagens de voz.');
    }

    /**
     * GET /api/v1/ai/corrections
     *
     * Retorna o histórico de correções gramaticais do usuário, paginado.
     */
    public function corrections(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 50);
        $data    = $this->tutor->getCorrections(auth()->user(), $perPage);

        return $this->success($data, 'Histórico de correções gramaticais.');
    }

    /**
     * GET /api/v1/ai/conversations
     *
     * Lista as conversas recentes do usuário.
     */
    public function conversations(): JsonResponse
    {
        $user          = auth()->user();
        $conversations = $this->tutor->getConversations($user);

        return $this->success(
            $conversations,
            'Conversas com o tutor.'
        );
    }

    /**
     * GET /api/v1/ai/usage
     *
     * Retorna estatísticas de consumo de tokens e custo estimado do usuário.
     * Separa all_time vs. mês atual e quebra por modelo.
     */
    public function usage(): JsonResponse
    {
        $user  = auth()->user();
        $stats = $this->tutor->getUsageStats($user);

        return $this->success($stats, 'Estatísticas de uso do tutor virtual.');
    }

    /**
     * GET /api/v1/ai/conversations/{conversation}
     *
     * Retorna o histórico completo de uma conversa.
     */
    public function history(AiConversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== auth()->id()) {
            return $this->unauthorized('Esta conversa não pertence a você.');
        }

        return $this->success([
            'conversation' => [
                'id'       => $conversation->id,
                'topic'    => $conversation->topic,
                'language' => $conversation->language,
                'level'    => $conversation->level,
            ],
            'messages' => $this->tutor->getHistory($conversation),
        ], 'Histórico da conversa.');
    }
}
