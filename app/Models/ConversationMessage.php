<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_session_id',
        'dialogue_line_id',
        'sender',
        'message',
        'expected_answer',
        'user_answer',
        'similarity_score',
        'quality_score',
        'is_correct',
        'is_user_turn',
        'line_order',
        'metadata',
        'sent_at',
    ];

    protected $casts = [
        'similarity_score' => 'float',
        'quality_score'    => 'integer',
        'is_correct'       => 'boolean',
        'is_user_turn'     => 'boolean',
        'line_order'       => 'integer',
        'metadata'         => 'array',
        'sent_at'          => 'datetime',
    ];

    // ─── Relacionamentos ────────────────────────────────────────────────────

    public function session(): BelongsTo
    {
        return $this->belongsTo(ConversationSession::class, 'conversation_session_id');
    }

    public function dialogueLine(): BelongsTo
    {
        return $this->belongsTo(DialogueLine::class);
    }

    // ─── Scopes ─────────────────────────────────────────────────────────────

    public function scopeFromApp($query)
    {
        return $query->where('sender', 'app');
    }

    public function scopeFromUser($query)
    {
        return $query->where('sender', 'user');
    }

    public function scopeUserTurns($query)
    {
        return $query->where('is_user_turn', true)->where('sender', 'user');
    }

    // ─── Accessors ──────────────────────────────────────────────────────────

    public function getIsFromAppAttribute(): bool
    {
        return $this->sender === 'app';
    }

    public function getIsFromUserAttribute(): bool
    {
        return $this->sender === 'user';
    }
}
