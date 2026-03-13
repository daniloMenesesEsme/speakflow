<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserExerciseAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'exercise_id',
        'answer',
        'correct',
        'points_earned',
        'attempt_number',
    ];

    protected $casts = [
        'correct'        => 'boolean',
        'points_earned'  => 'integer',
        'attempt_number' => 'integer',
        'created_at'     => 'datetime',
    ];

    // ─── Relacionamentos ─────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    /** Apenas tentativas corretas. */
    public function scopeCorrect($query)
    {
        return $query->where('correct', true);
    }

    /** Tentativas de um usuário específico. */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
