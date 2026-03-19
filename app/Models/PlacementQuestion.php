<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlacementQuestion extends Model
{
    protected $fillable = [
        'question',
        'options',
        'correct_answer',
        'skill',
        'cefr_level',
        'display_order',
        'weight',
        'is_active',
    ];

    protected $casts = [
        'options' => 'array',
        'weight' => 'float',
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

