<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPlacementResult extends Model
{
    protected $fillable = [
        'user_id',
        'total_questions',
        'correct_answers',
        'score_percentage',
        'level',
        'skill_breakdown',
        'answers',
    ];

    protected $casts = [
        'total_questions' => 'integer',
        'correct_answers' => 'integer',
        'score_percentage' => 'float',
        'skill_breakdown' => 'array',
        'answers' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

