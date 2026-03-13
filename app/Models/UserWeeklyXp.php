<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserWeeklyXp extends Model
{
    protected $table = 'user_weekly_xp';

    protected $fillable = [
        'user_id',
        'week_start',
        'xp',
        'lessons_completed',
        'exercises_completed',
    ];

    protected $casts = [
        'week_start'          => 'date',
        'xp'                  => 'integer',
        'lessons_completed'   => 'integer',
        'exercises_completed' => 'integer',
    ];

    // ─── Relacionamentos ─────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Helpers estáticos ───────────────────────────────────────────────────

    /**
     * Retorna o início da semana atual (segunda-feira) como string 'Y-m-d'.
     * Padrão ISO: semana começa na segunda-feira.
     */
    public static function currentWeekStart(): string
    {
        return now()->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeCurrentWeek($query)
    {
        return $query->where('week_start', static::currentWeekStart());
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
