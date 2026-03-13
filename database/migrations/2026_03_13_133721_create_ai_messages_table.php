<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversation_id')
                ->constrained('ai_conversations')
                ->cascadeOnDelete();

            // 'user' | 'assistant' | 'system'
            $table->string('role', 20);

            $table->text('content');

            // Tokens desta mensagem (preenchido para respostas do assistant)
            $table->unsignedSmallInteger('tokens')->default(0);

            // Modelo usado (ex: gpt-4o-mini)
            $table->string('model', 50)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['conversation_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
    }
};
