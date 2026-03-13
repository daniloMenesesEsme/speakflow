<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_limits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Data do contador (um registro por usuário por dia)
            $table->date('date');

            // Contadores diários
            $table->unsignedSmallInteger('ai_messages')->default(0);
            $table->unsignedSmallInteger('voice_messages')->default(0);

            $table->timestamps();

            // Garante 1 linha por usuário por dia
            $table->unique(['user_id', 'date']);
            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_limits');
    }
};
