<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'price',
        'billing_cycle',
        'features',
        'is_featured',
        'active',
    ];

    protected $casts = [
        'price'       => 'float',
        'features'    => 'array',
        'is_featured' => 'boolean',
        'active'      => 'boolean',
    ];

    // ─── Slugs dos planos conhecidos ─────────────────────────────────────────

    public const FREE    = 'free';
    public const PRO     = 'pro';
    public const PREMIUM = 'premium';

    // ─── Feature keys ─────────────────────────────────────────────────────────

    public const FEAT_AI_PER_DAY      = 'ai_messages_per_day';
    public const FEAT_VOICE_PER_DAY   = 'voice_messages_per_day';
    public const FEAT_LESSONS_PER_DAY = 'lessons_per_day';
    public const FEAT_VOICE_MINUTES   = 'voice_minutes_per_day';
    public const FEAT_UNLIMITED       = 'unlimited';

    // ─── Features identificadas por string (usadas em canUseFeature) ─────────

    public const FEATURE_AI_MESSAGE        = 'ai_message';
    public const FEATURE_VOICE_MESSAGE     = 'voice_message';
    public const FEATURE_LESSON_GENERATION = 'lesson_generation';

    // ─── Relacionamentos ─────────────────────────────────────────────────────

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function getAiLimit(): int
    {
        if ($this->features[self::FEAT_UNLIMITED] ?? false) {
            return PHP_INT_MAX;
        }

        return (int) ($this->features[self::FEAT_AI_PER_DAY] ?? 5);
    }

    public function getVoiceLimit(): int
    {
        if ($this->features[self::FEAT_UNLIMITED] ?? false) {
            return PHP_INT_MAX;
        }

        return (int) ($this->features[self::FEAT_VOICE_PER_DAY] ?? 2);
    }

    public function getLessonLimit(): int
    {
        if ($this->features[self::FEAT_UNLIMITED] ?? false) {
            return PHP_INT_MAX;
        }

        return (int) ($this->features[self::FEAT_LESSONS_PER_DAY] ?? 2);
    }

    public function getVoiceMinutesLimit(): int
    {
        if ($this->features[self::FEAT_UNLIMITED] ?? false) {
            return PHP_INT_MAX;
        }

        return (int) ($this->features[self::FEAT_VOICE_MINUTES] ?? 5);
    }

    /**
     * Retorna o limite diário para qualquer feature identificada por string.
     */
    public function getLimitFor(string $feature): int
    {
        return match ($feature) {
            self::FEATURE_AI_MESSAGE        => $this->getAiLimit(),
            self::FEATURE_VOICE_MESSAGE     => $this->getVoiceLimit(),
            self::FEATURE_LESSON_GENERATION => $this->getLessonLimit(),
            default                         => PHP_INT_MAX,
        };
    }

    public function isUnlimited(): bool
    {
        return (bool) ($this->features[self::FEAT_UNLIMITED] ?? false);
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
