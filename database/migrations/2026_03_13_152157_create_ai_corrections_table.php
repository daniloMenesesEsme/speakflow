<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_corrections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Conversa em que a correção ocorreu (opcional: pode ocorrer fora de uma conversa)
            $table->foreignId('conversation_id')
                ->nullable()
                ->constrained('ai_conversations')
                ->nullOnDelete();

            $table->text('original_text');
            $table->text('corrected_text');
            $table->text('explanation');

            // Língua-alvo da correção (en, es, fr, ...)
            $table->string('language', 10)->default('en');

            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'language']);
            $table->index('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_corrections');
    }
};
