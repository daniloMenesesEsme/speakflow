<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_daily_activity', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Um registro por usuário por dia
            $table->date('date');

            $table->unsignedSmallInteger('xp_earned')->default(0)
                ->comment('XP total acumulado neste dia');

            $table->unsignedSmallInteger('lessons_completed')->default(0)
                ->comment('Lições concluídas neste dia');

            $table->unsignedSmallInteger('exercises_answered')->default(0)
                ->comment('Exercícios respondidos (corretos) neste dia');

            $table->timestamps();

            $table->unique(['user_id', 'date']);
            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_daily_activity');
    }
};
