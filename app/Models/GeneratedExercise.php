<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedExercise extends Model
{
    protected $fillable = [
        'lesson_id',
        'type',
        'order',
        'question',
        'sentence',
        'correct_answer',
        'options',
        'hint',
        'explanation',
        'xp_reward',
    ];

    protected $casts = [
        'options'   => 'array',
        'order'     => 'integer',
        'xp_reward' => 'integer',
    ];

    // ─── Tipos suportados ─────────────────────────────────────────────────────

    public const TYPE_FILL_BLANK          = 'fill_blank';
    public const TYPE_MULTIPLE_CHOICE     = 'multiple_choice';
    public const TYPE_SENTENCE_CORRECTION = 'sentence_correction';
    public const TYPE_TRANSLATION         = 'translation';
    public const TYPE_TRUE_FALSE          = 'true_false';

    public const TYPES = [
        self::TYPE_FILL_BLANK,
        self::TYPE_MULTIPLE_CHOICE,
        self::TYPE_SENTENCE_CORRECTION,
        self::TYPE_TRANSLATION,
        self::TYPE_TRUE_FALSE,
    ];

    // ─── Relacionamentos ─────────────────────────────────────────────────────

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(GeneratedLesson::class, 'lesson_id');
    }
}
