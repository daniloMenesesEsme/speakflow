<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dialogue extends Model
{
    use HasFactory;

    // Temas padronizados suportados pelo sistema offline
    public const TOPICS = [
        'greetings'   => 'Saudações e Apresentações',
        'restaurant'  => 'Restaurante',
        'airport'     => 'Aeroporto',
        'hotel'       => 'Hotel',
        'shopping'    => 'Compras',
        'transport'   => 'Transporte',
        'health'      => 'Saúde e Farmácia',
        'work'        => 'Trabalho e Escritório',
        'school'      => 'Escola e Estudo',
        'directions'  => 'Direções e Lugares',
        'family'      => 'Família e Amigos',
        'weather'     => 'Clima e Tempo',
    ];

    protected $fillable = [
        'language_id',
        'topic',
        'slug',
        'topic_category',
        'level',
        'description',
        'context',
        'estimated_minutes',
        'total_turns',
        'is_active',
    ];

    protected $casts = [
        'is_active'          => 'boolean',
        'estimated_minutes'  => 'integer',
        'total_turns'        => 'integer',
    ];

    // ─── Relacionamentos ────────────────────────────────────────────────────

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DialogueLine::class)->orderBy('order');
    }

    public function conversationSessions(): HasMany
    {
        return $this->hasMany(ConversationSession::class);
    }

    // ─── Scopes ─────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    public function scopeByTopic($query, string $slug)
    {
        return $query->where('slug', $slug);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('topic_category', $category);
    }

    public function scopeForLanguage($query, string $languageCode)
    {
        return $query->whereHas('language', fn ($q) => $q->where('code', $languageCode));
    }

    public function scopeForUser($query, User $user)
    {
        return $query
            ->forLanguage($user->target_language)
            ->byLevel($user->level)
            ->active();
    }

    // ─── Helpers estáticos ──────────────────────────────────────────────────

    public static function topicLabel(string $slug): string
    {
        return self::TOPICS[$slug] ?? ucfirst($slug);
    }

    public static function topicExists(string $slug): bool
    {
        return array_key_exists($slug, self::TOPICS);
    }

    // ─── Accessor ───────────────────────────────────────────────────────────

    public function getTopicLabelAttribute(): string
    {
        return self::topicLabel($this->slug ?? $this->topic);
    }
}
