<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDailyActivity extends Model
{
    protected $table = 'user_daily_activity';

    protected $fillable = [
        'user_id',
        'date',
        'xp_earned',
        'lessons_completed',
        'exercises_answered',
    ];

    protected $casts = [
        'date'               => 'date',
        'xp_earned'          => 'integer',
        'lessons_completed'  => 'integer',
        'exercises_answered' => 'integer',
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

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('date', '>=', now()->subDays($days)->toDateString());
    }
}
