<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDailyMission extends Model
{
    protected $fillable = [
        'user_id',
        'mission_id',
        'progress',
        'date',
        'completed',
        'completed_at',
    ];

    protected $casts = [
        'progress'     => 'integer',
        'completed'    => 'boolean',
        'date'         => 'date',
        'completed_at' => 'datetime',
    ];

    // ─── Relacionamentos ─────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(DailyMission::class, 'mission_id');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Percentual de conclusão (0–100).
     */
    public function percentComplete(): int
    {
        if (!$this->mission || $this->mission->target <= 0) {
            return 0;
        }

        return (int) min(100, round(($this->progress / $this->mission->target) * 100));
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeToday($query)
    {
        return $query->where('date', now()->toDateString());
    }

    public function scopeCompleted($query)
    {
        return $query->where('completed', true);
    }

    public function scopePending($query)
    {
        return $query->where('completed', false);
    }
}
