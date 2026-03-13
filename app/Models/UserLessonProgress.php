<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLessonProgress extends Model
{
    use HasFactory;

    protected $table = 'user_lesson_progress';

    protected $fillable = [
        'user_id',
        'lesson_id',
        'completion_percentage',
        'times_completed',
        'best_score',
        'last_accessed_at',
        'completed',
        'xp_earned',
        'completed_at',
    ];

    protected $casts = [
        'completion_percentage' => 'integer',
        'times_completed'       => 'integer',
        'best_score'            => 'integer',
        'xp_earned'             => 'integer',
        'completed'             => 'boolean',
        'last_accessed_at'      => 'datetime',
        'completed_at'          => 'datetime',
    ];

    // ─── Relacionamentos ─────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Verifica se a lição foi formalmente concluída.
     * A flag `completed` é a fonte da verdade; completion_percentage é auxiliar.
     */
    public function isCompleted(): bool
    {
        return $this->completed === true;
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeCompleted($query)
    {
        return $query->where('completed', true);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
