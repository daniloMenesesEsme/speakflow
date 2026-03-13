<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'plan_id',
        'status',
        'started_at',
        'expires_at',
        'payment_reference',
        'notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // ─── Status válidos ───────────────────────────────────────────────────────

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_EXPIRED  = 'expired';
    public const STATUS_TRIALING = 'trialing';

    // ─── Relacionamentos ─────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE && $this->status !== self::STATUS_TRIALING) {
            return false;
        }

        // Sem data de expiração = vitalício (ex: plano free)
        if ($this->expires_at === null) {
            return true;
        }

        return $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function daysUntilExpiry(): ?int
    {
        if ($this->expires_at === null) {
            return null;
        }

        return max(0, (int) now()->diffInDays($this->expires_at, false));
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where(fn ($q) => $q
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now())
            );
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
