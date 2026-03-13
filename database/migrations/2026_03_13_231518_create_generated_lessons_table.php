<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_lessons', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Parâmetros que originaram a geração
            $table->string('level', 10);         // A1, A2, B1 ...
            $table->string('topic', 150);        // ex: "Food and Restaurant"
            $table->json('grammar_focus');       // ex: ["want to + infinitive"]

            // Conteúdo gerado
            $table->string('lesson_title', 200);
            $table->text('lesson_introduction')->nullable();

            // Qual driver gerou: 'openai' ou 'offline'
            $table->string('driver', 20)->default('offline');

            // Tokens consumidos (se OpenAI)
            $table->unsignedSmallInteger('prompt_tokens')->default(0);
            $table->unsignedSmallInteger('completion_tokens')->default(0);
            $table->decimal('estimated_cost', 10, 8)->default(0);

            // Permitir reusar lições geradas para outros usuários no futuro
            $table->boolean('public')->default(false);

            $table->timestamps();

            $table->index(['user_id', 'level']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_lessons');
    }
};
