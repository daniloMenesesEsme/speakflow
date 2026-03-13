<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeneratedLesson extends Model
{
    protected $fillable = [
        'user_id',
        'level',
        'topic',
        'grammar_focus',
        'lesson_title',
        'lesson_introduction',
        'driver',
        'prompt_tokens',
        'completion_tokens',
        'estimated_cost',
        'public',
    ];

    protected $casts = [
        'grammar_focus'    => 'array',
        'prompt_tokens'    => 'integer',
        'completion_tokens'=> 'integer',
        'estimated_cost'   => 'float',
        'public'           => 'boolean',
    ];

    // ─── Drivers ─────────────────────────────────────────────────────────────

    public const DRIVER_OPENAI  = 'openai';
    public const DRIVER_OFFLINE = 'offline';

    // ─── Relacionamentos ─────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(GeneratedExercise::class, 'lesson_id')->orderBy('order');
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByLevel($query, string $level)
    {
        return $query->where('level', $level);
    }
}
