<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AiVoiceMessage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'conversation_id',
        'audio_path',
        'audio_format',
        'duration_seconds',
        'transcription',
        'detected_language',
        'transcription_driver',
        'processing_time_ms',
    ];

    protected $casts = [
        'duration_seconds'  => 'integer',
        'processing_time_ms'=> 'integer',
        'created_at'        => 'datetime',
    ];

    // ─── Formatos de áudio aceitos pelo Whisper ───────────────────────────────

    public const SUPPORTED_FORMATS = ['flac', 'm4a', 'mp3', 'mp4', 'mpeg', 'mpga', 'oga', 'ogg', 'wav', 'webm'];

    // ─── Relacionamentos ─────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Retorna a URL pública do áudio (se armazenado no disco 'public').
     * Para disco 'local', use Storage::path() para acesso interno.
     */
    public function audioUrl(): ?string
    {
        return $this->audio_path
            ? Storage::url($this->audio_path)
            : null;
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeTranscribed($query)
    {
        return $query->whereNotNull('transcription');
    }
}
