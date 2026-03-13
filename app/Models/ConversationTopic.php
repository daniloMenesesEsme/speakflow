<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ConversationTopic extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'level',
        'icon',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    // ─── Boot ────────────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $topic) {
            if (empty($topic->slug)) {
                $topic->slug = Str::slug($topic->title);
            }
        });
    }

    // ─── Relacionamentos ─────────────────────────────────────────────────────

    public function conversations(): HasMany
    {
        return $this->hasMany(AiConversation::class, 'topic_id');
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeForLevel($query, string $level)
    {
        // Ordenação CEFR: retorna tópicos acessíveis ao nível informado
        $order = ['A1' => 1, 'A2' => 2, 'B1' => 3, 'B2' => 4, 'C1' => 5, 'C2' => 6];
        $max   = $order[$level] ?? 6;

        return $query->whereIn('level', array_keys(
            array_filter($order, fn ($v) => $v <= $max)
        ));
    }
}
