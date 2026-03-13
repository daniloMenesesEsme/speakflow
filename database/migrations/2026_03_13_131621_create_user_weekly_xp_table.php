<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_weekly_xp', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Sempre a segunda-feira da semana (início ISO da semana)
            $table->date('week_start');

            $table->unsignedInteger('xp')->default(0)
                ->comment('XP total acumulado nesta semana');

            $table->unsignedSmallInteger('lessons_completed')->default(0)
                ->comment('Lições concluídas nesta semana');

            $table->unsignedSmallInteger('exercises_completed')->default(0)
                ->comment('Exercícios respondidos corretamente nesta semana');

            $table->timestamps();

            $table->unique(['user_id', 'week_start']);
            $table->index(['week_start', 'xp']);       // ranking por semana
            $table->index(['user_id', 'week_start']);  // histórico por usuário
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_weekly_xp');
    }
};
