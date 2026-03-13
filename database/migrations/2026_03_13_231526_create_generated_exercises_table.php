<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_exercises', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lesson_id')
                ->constrained('generated_lessons')
                ->cascadeOnDelete();

            // Tipo do exercício
            $table->string('type', 30);
            // fill_blank | multiple_choice | sentence_correction | translation | true_false

            // Ordem de exibição na lição
            $table->unsignedTinyInteger('order')->default(1);

            // Instrução/pergunta exibida ao usuário
            $table->text('question');

            // Texto com lacuna para fill_blank (ex: "I ___ go to school.")
            $table->text('sentence')->nullable();

            // Resposta correta
            $table->text('correct_answer');

            // Opções para multiple_choice (JSON array de strings)
            $table->json('options')->nullable();

            // Dica opcional para o usuário
            $table->text('hint')->nullable();

            // Explicação didática da resposta
            $table->text('explanation')->nullable();

            // XP concedido ao acertar este exercício
            $table->unsignedTinyInteger('xp_reward')->default(10);

            $table->timestamps();

            $table->index(['lesson_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_exercises');
    }
};
