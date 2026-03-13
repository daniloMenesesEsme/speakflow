<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_missions', function (Blueprint $table) {
            $table->id();

            // Tipo de atividade que a missão acompanha
            $table->string('type', 30); // exercise | lesson | conversation | voice_message

            // Meta que o usuário precisa atingir
            $table->unsignedSmallInteger('target');

            // Recompensa em XP ao concluir
            $table->unsignedSmallInteger('xp_reward');

            // Título curto exibido no app
            $table->string('title', 100);

            // Descrição motivacional
            $table->string('description', 255)->nullable();

            // Ícone/emoji representativo
            $table->string('icon', 10)->nullable();

            // Permite desativar uma missão sem removê-la
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index('type');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_missions');
    }
};
