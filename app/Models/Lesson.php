<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'language_id',
        'title',
        'level',
        'category',
        'order',
        'description',
        'thumbnail',
        'xp_reward',
        'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'xp_reward'  => 'integer',
        'order'      => 'integer',
    ];

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function phrases(): HasMany
    {
        return $this->hasMany(Phrase::class);
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class)->orderBy('order');
    }

    public function studySessions(): HasMany
    {
        return $this->hasMany(StudySession::class);
    }

    public function userProgress(): HasMany
    {
        return $this->hasMany(UserLessonProgress::class);
    }

    /**
     * Progresso do usuário autenticado nesta lição.
     * Alias singular para uso em contextos autenticados.
     */
    public function progress(): HasMany
    {
        return $this->hasMany(UserLessonProgress::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}
