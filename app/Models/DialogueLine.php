<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DialogueLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'dialogue_id',
        'speaker',
        'text',
        'expected_answer',
        'translation',
        'audio_path',
        'order',
        'is_user_turn',
        'hints',
    ];

    protected $casts = [
        'is_user_turn' => 'boolean',
        'hints'        => 'array',
        'order'        => 'integer',
    ];

    public function dialogue(): BelongsTo
    {
        return $this->belongsTo(Dialogue::class);
    }
}
