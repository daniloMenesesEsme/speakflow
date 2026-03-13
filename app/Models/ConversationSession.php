<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConversationSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'dialogue_id',
        'topic_slug',
        'status',
        'current_line_order',
        'total_lines',
        'user_turns_total',
        'user_turns_correct',
        'total_score',
        'messages_count',
        'xp_earned',
        'started_at',
        'completed_at',
        'last_activity_at',
    ];

    protected $casts = [
        'current_line_order'  => 'integer',
        'total_lines'         => 'integer',
        'user_turns_total'    => 'integer',
        'user_turns_correct'  => 'integer',
        'total_score'         => 'integer',
        'messages_count'      => 'integer',
        'xp_earned'           => 'integer',
        'started_at'          => 'datetime',
        'completed_at'        => 'datetime',
        'last_activity_at'    => 'datetime',
    ];

    // ─── Relacionamentos ────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dialogue(): BelongsTo
    {
        return $this->belongsTo(Dialogue::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class)->orderBy('line_order');
    }

    // ─── Scopes ─────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByTopic($query, string $slug)
    {
        return $query->where('topic_slug', $slug);
    }

    // ─── Accessors ──────────────────────────────────────────────────────────

    public function getAccuracyPercentageAttribute(): float
    {
        if ($this->user_turns_total === 0) {
            return 0.0;
        }

        return round(($this->user_turns_correct / $this->user_turns_total) * 100, 2);
    }

    public function getAverageScoreAttribute(): float
    {
        if ($this->user_turns_total === 0) {
            return 0.0;
        }

        return round($this->total_score / $this->user_turns_total, 2);
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_lines === 0) {
            return 0.0;
        }

        return round(($this->current_line_order / $this->total_lines) * 100, 2);
    }

    public function getGradeAttribute(): string
    {
        $avg = $this->average_score;

        return match (true) {
            $avg >= 90 => 'A',
            $avg >= 80 => 'B',
            $avg >= 70 => 'C',
            $avg >= 60 => 'D',
            default    => 'F',
        };
    }

    public function getDurationMinutesAttribute(): int
    {
        $end   = $this->completed_at ?? Carbon::now();
        $start = $this->started_at ?? $this->created_at;

        return max(0, (int) $start->diffInMinutes($end));
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function touchActivity(): void
    {
        $this->last_activity_at = Carbon::now();
        $this->save();
    }

    public function markCompleted(int $xpEarned = 0): void
    {
        $this->status       = 'completed';
        $this->completed_at = Carbon::now();
        $this->xp_earned    = $xpEarned;
        $this->save();
    }
}
