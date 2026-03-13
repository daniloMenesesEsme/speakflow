<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhraseReviewState extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'phrase_id',
        'repetitions',
        'ease_factor',
        'interval_days',
        'next_review_at',
        'last_reviewed_at',
        'last_quality',
        'total_reviews',
        'cumulative_score',
    ];

    protected $casts = [
        'repetitions'       => 'integer',
        'ease_factor'       => 'decimal:2',
        'interval_days'     => 'integer',
        'next_review_at'    => 'datetime',
        'last_reviewed_at'  => 'datetime',
        'last_quality'      => 'integer',
        'total_reviews'     => 'integer',
        'cumulative_score'  => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function phrase(): BelongsTo
    {
        return $this->belongsTo(Phrase::class);
    }

    public function isDueForReview(): bool
    {
        if ($this->next_review_at === null) {
            return true;
        }

        return Carbon::now()->greaterThanOrEqualTo($this->next_review_at);
    }

    public function getAverageQualityAttribute(): float
    {
        if ($this->total_reviews === 0) {
            return 0.0;
        }

        return round($this->cumulative_score / $this->total_reviews, 2);
    }

    public function getDaysUntilReviewAttribute(): int
    {
        if ($this->isDueForReview()) {
            return 0;
        }

        return (int) Carbon::now()->diffInDays($this->next_review_at);
    }

    public function getMasteryLevelAttribute(): string
    {
        return match (true) {
            $this->repetitions >= 10 && $this->ease_factor >= 2.5  => 'mastered',
            $this->repetitions >= 5                                 => 'learned',
            $this->repetitions >= 2                                 => 'familiar',
            $this->repetitions >= 1                                 => 'seen',
            default                                                 => 'new',
        };
    }

    public function scopeDueForReview($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('next_review_at')
              ->orWhere('next_review_at', '<=', Carbon::now());
        });
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
