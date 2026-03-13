<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Histórico completo de mensagens de cada sessão de conversa.
     * Cada linha do diálogo gera uma ou duas mensagens (app + user).
     * Funciona como log imutável para análise e retomada offline.
     */
    public function up(): void
    {
        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_session_id')
                ->constrained('conversation_sessions')
                ->cascadeOnDelete();
            $table->foreignId('dialogue_line_id')
                ->nullable()
                ->constrained('dialogue_lines')
                ->nullOnDelete();

            $table->string('sender', 10);
            // sender: 'app' | 'user'

            $table->text('message');
            $table->text('expected_answer')->nullable();
            $table->text('user_answer')->nullable();

            $table->decimal('similarity_score', 5, 4)->default(0);
            $table->unsignedTinyInteger('quality_score')->default(0);
            // quality_score: 0–100 (proporcional à similaridade)

            $table->boolean('is_correct')->default(false);
            $table->boolean('is_user_turn')->default(false);
            $table->unsignedSmallInteger('line_order')->default(0);

            $table->json('metadata')->nullable();
            // metadata: hints usados, número de tentativas, etc.

            $table->timestamp('sent_at')->useCurrent();
            $table->timestamps();

            $table->index(['conversation_session_id', 'line_order']);
            $table->index(['conversation_session_id', 'sender']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_messages');
    }
};
