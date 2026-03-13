<?php

namespace App\Models;

use App\Models\PhraseReviewState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Phrase extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'english_text',
        'portuguese_text',
        'audio_path',
        'difficulty',
        'phonetic',
        'tags',
    ];

    protected $casts = [
        'tags' => 'array',
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function pronunciationScores(): HasMany
    {
        return $this->hasMany(PronunciationScore::class);
    }

    public function reviewState(): HasMany
    {
        return $this->hasMany(PhraseReviewState::class);
    }

    public function getAverageScoreAttribute(): float
    {
        return round($this->pronunciationScores()->avg('score') ?? 0, 2);
    }

    public function scopeByDifficulty($query, string $difficulty)
    {
        return $query->where('difficulty', $difficulty);
    }
}
