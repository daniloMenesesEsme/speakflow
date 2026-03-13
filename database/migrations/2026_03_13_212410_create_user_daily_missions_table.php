<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_daily_missions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('mission_id')
                ->constrained('daily_missions')
                ->cascadeOnDelete();

            // Progresso atual do usuário nesta missão (ex: 2 de 3 exercícios)
            $table->unsignedSmallInteger('progress')->default(0);

            // Data da missão (uma por dia por missão por usuário)
            $table->date('date');

            $table->boolean('completed')->default(false);
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            // Garante que cada usuário tenha só 1 registro por missão por dia
            $table->unique(['user_id', 'mission_id', 'date']);
            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'completed', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_daily_missions');
    }
};
