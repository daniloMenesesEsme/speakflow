<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageLimit extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'ai_messages',
        'voice_messages',
        'lesson_generation',
    ];

    protected $casts = [
        'date'              => 'date',
        'ai_messages'       => 'integer',
        'voice_messages'    => 'integer',
        'lesson_generation' => 'integer',
    ];

    // ─── Relacionamentos ─────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Helpers estáticos ────────────────────────────────────────────────────

    /**
     * Retorna (ou cria) o registro de uso do dia atual para um usuário.
     */
    public static function todayFor(int $userId): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId, 'date' => now()->toDateString()],
            ['ai_messages' => 0, 'voice_messages' => 0, 'lesson_generation' => 0]
        );
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeToday($query)
    {
        return $query->where('date', now()->toDateString());
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
