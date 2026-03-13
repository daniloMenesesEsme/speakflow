<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyMission extends Model
{
    protected $fillable = [
        'type',
        'target',
        'xp_reward',
        'title',
        'description',
        'icon',
        'active',
    ];

    protected $casts = [
        'target'    => 'integer',
        'xp_reward' => 'integer',
        'active'    => 'boolean',
    ];

    // Tipos válidos de missão
    public const TYPE_EXERCISE     = 'exercise';
    public const TYPE_LESSON       = 'lesson';
    public const TYPE_CONVERSATION = 'conversation';
    public const TYPE_VOICE        = 'voice_message';

    public const TYPES = [
        self::TYPE_EXERCISE,
        self::TYPE_LESSON,
        self::TYPE_CONVERSATION,
        self::TYPE_VOICE,
    ];

    // ─── Relacionamentos ─────────────────────────────────────────────────────

    public function userMissions(): HasMany
    {
        return $this->hasMany(UserDailyMission::class, 'mission_id');
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
