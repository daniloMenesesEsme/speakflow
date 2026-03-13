<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Código ISO do idioma da conversa (ex: 'en', 'es')
            $table->string('language', 10)->default('en');

            // Tema livre escolhido pelo usuário (restaurant, travel, work...)
            $table->string('topic', 100)->nullable();

            // Nível CEFR do usuário no momento da conversa
            $table->string('level', 5)->default('A1');

            // Número de mensagens trocadas
            $table->unsignedSmallInteger('messages_count')->default(0);

            // Tokens consumidos nesta conversa (para monitoramento de custo)
            $table->unsignedInteger('total_tokens')->default(0);

            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'language']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_conversations');
    }
};
