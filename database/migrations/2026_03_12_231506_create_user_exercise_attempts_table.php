<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_exercise_attempts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('exercise_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->text('answer');
            $table->boolean('correct')->default(false);

            // Pontos efetivamente concedidos nesta tentativa.
            // Apenas a primeira resposta correta concede XP; tentativas
            // subsequentes registram 0 para evitar farm de pontos.
            $table->unsignedSmallInteger('points_earned')->default(0);

            $table->unsignedSmallInteger('attempt_number')->default(1)
                ->comment('Número sequencial de tentativas do usuário neste exercício');

            $table->timestamp('created_at')->useCurrent();

            // Índices para consultas frequentes
            $table->index(['user_id', 'exercise_id']);
            $table->index(['user_id', 'correct']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_exercise_attempts');
    }
};
