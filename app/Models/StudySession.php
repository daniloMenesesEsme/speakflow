<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudySession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'lesson_id',
        'duration_minutes',
        'xp_earned',
        'exercises_completed',
        'correct_answers',
        'accuracy_percentage',
        'completed',
    ];

    protected $casts = [
        'duration_minutes'    => 'integer',
        'xp_earned'           => 'integer',
        'exercises_completed' => 'integer',
        'correct_answers'     => 'integer',
        'accuracy_percentage' => 'decimal:2',
        'completed'           => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
