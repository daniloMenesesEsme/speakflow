<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiConversation extends Model
{
    protected $fillable = [
        'user_id',
        'language',
        'topic',
        'topic_id',
        'level',
        'messages_count',
        'total_tokens',
        'last_message_at',
    ];

    protected $casts = [
        'messages_count'  => 'integer',
        'total_tokens'    => 'integer',
        'last_message_at' => 'datetime',
    ];

    // ─── Relacionamentos ─────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversationTopic(): BelongsTo
    {
        return $this->belongsTo(ConversationTopic::class, 'topic_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'conversation_id')
            ->orderBy('created_at');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(AiCorrection::class, 'conversation_id');
    }

    public function voiceMessages(): HasMany
    {
        return $this->hasMany(AiVoiceMessage::class, 'conversation_id');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Retorna o histórico formatado para a API OpenAI (role + content).
     * Exclui mensagens de sistema para não duplicar o system prompt.
     */
    public function toOpenAiHistory(int $maxMessages = 20): array
    {
        return $this->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->latest('created_at')
            ->limit($maxMessages)
            ->get()
            ->sortBy('created_at')
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->toArray();
    }
}
