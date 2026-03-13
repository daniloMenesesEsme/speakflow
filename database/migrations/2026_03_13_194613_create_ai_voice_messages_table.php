<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_voice_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('conversation_id')
                ->nullable()
                ->constrained('ai_conversations')
                ->nullOnDelete();

            // Caminho relativo ao disco 'local' (storage/app/voice_messages/...)
            $table->string('audio_path', 500);

            // Formato detectado/enviado (webm, mp3, wav, m4a, ogg, flac)
            $table->string('audio_format', 10)->default('webm');

            // Duração em segundos (preenchida quando disponível)
            $table->unsignedSmallInteger('duration_seconds')->nullable();

            // Transcrição retornada pelo Whisper (ou fallback)
            $table->text('transcription')->nullable();

            // Língua detectada pelo Whisper
            $table->string('detected_language', 10)->nullable();

            // Driver usado: 'whisper-1' | 'mock'
            $table->string('transcription_driver', 30)->default('mock');

            // Tempo de processamento da transcrição em ms
            $table->unsignedInteger('processing_time_ms')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_voice_messages');
    }
};
