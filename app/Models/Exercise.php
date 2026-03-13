<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exercise extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'type',
        'question',
        'correct_answer',
        'options',
        'explanation',
        'difficulty',
        'order',
        'points',
    ];

    protected $casts = [
        'options' => 'array',
        'order'   => 'integer',
        'points'  => 'integer',
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(UserExerciseAttempt::class);
    }

    public function checkAnswer(string $answer): bool
    {
        return strtolower(trim($answer)) === strtolower(trim($this->correct_answer));
    }
}
