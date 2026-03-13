<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_topics', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);
            $table->string('slug', 120)->unique();
            $table->text('description');

            // Nível CEFR mínimo recomendado para o tópico
            $table->string('level', 5)->default('A1');

            // Ícone/emoji representativo (opcional, exibido no app)
            $table->string('icon', 10)->nullable();

            // Permite desativar um tópico sem removê-lo
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index('level');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_topics');
    }
};
