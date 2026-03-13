<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PronunciationScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'phrase_id',
        'score',
        'accuracy',
        'fluency',
        'confidence',
        'transcription',
        'driver',
        'processing_time_ms',
        'audio_path',
        'phoneme_scores',
        'feedback',
    ];

    protected $casts = [
        'score'              => 'decimal:2',
        'accuracy'           => 'decimal:2',
        'fluency'            => 'decimal:2',
        'confidence'         => 'decimal:2',
        'phoneme_scores'     => 'array',
        'processing_time_ms' => 'integer',
    ];

    // ─── Relacionamentos ────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function phrase(): BelongsTo
    {
        return $this->belongsTo(Phrase::class);
    }

    // ─── Scopes ─────────────────────────────────────────────────────────────

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeExcellent($query)
    {
        return $query->where('score', '>=', 90);
    }

    public function scopePassing($query)
    {
        return $query->where('score', '>=', 60);
    }

    public function scopeByDriver($query, string $driver)
    {
        return $query->where('driver', $driver);
    }

    // ─── Accessors ──────────────────────────────────────────────────────────

    public function getGradeAttribute(): string
    {
        return match (true) {
            $this->score >= 90 => 'A',
            $this->score >= 80 => 'B',
            $this->score >= 70 => 'C',
            $this->score >= 60 => 'D',
            default            => 'F',
        };
    }

    public function getAccuracyLabelAttribute(): string
    {
        return $this->metricLabel((float) $this->accuracy);
    }

    public function getFluencyLabelAttribute(): string
    {
        return $this->metricLabel((float) $this->fluency);
    }

    public function getConfidenceLabelAttribute(): string
    {
        return $this->metricLabel((float) $this->confidence);
    }

    public function getIsExcellentAttribute(): bool
    {
        return $this->score >= 90;
    }

    public function getIsPassingAttribute(): bool
    {
        return $this->score >= 60;
    }

    public function getWeakestMetricAttribute(): string
    {
        $metrics = [
            'accuracy'   => (float) $this->accuracy,
            'fluency'    => (float) $this->fluency,
            'confidence' => (float) $this->confidence,
        ];

        return array_key_first(array_filter(
            $metrics,
            fn ($v) => $v === min($metrics)
        ));
    }

    // ─── Helpers privados ───────────────────────────────────────────────────

    private function metricLabel(float $value): string
    {
        return match (true) {
            $value >= 90 => 'Excelente',
            $value >= 75 => 'Bom',
            $value >= 60 => 'Regular',
            default      => 'Precisa melhorar',
        };
    }
}
