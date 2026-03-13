<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageLog extends Model
{
    public const UPDATED_AT = null; // tabela só tem created_at

    protected $fillable = [
        'user_id',
        'type',
        'quantity',
        'metadata',
        'plan_slug',
    ];

    protected $casts = [
        'quantity'   => 'integer',
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    // ─── Tipos de uso ─────────────────────────────────────────────────────────

    public const TYPE_AI_MESSAGE        = 'ai_message';
    public const TYPE_LESSON_GENERATION = 'lesson_generation';
    public const TYPE_VOICE_MESSAGE     = 'voice_message';

    public const TYPES = [
        self::TYPE_AI_MESSAGE,
        self::TYPE_LESSON_GENERATION,
        self::TYPE_VOICE_MESSAGE,
    ];

    // ─── Relacionamentos ─────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', now()->toDateString());
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);
    }
}
