<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Armazena o estado individual de revisão de cada frase por usuário.
     * Implementa o algoritmo SM-2 (SuperMemo 2) para Spaced Repetition.
     *
     * Campos do algoritmo SM-2:
     *   - repetitions:  quantas vezes seguidas o usuário acertou (qualidade >= 3)
     *   - ease_factor:  fator de facilidade, ajusta o intervalo (mín 1.3, padrão 2.5)
     *   - interval_days: dias até a próxima revisão
     *   - next_review_at: data/hora exata da próxima revisão programada
     *   - last_quality:  última nota de qualidade dada (0–5)
     */
    public function up(): void
    {
        Schema::create('phrase_review_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('phrase_id')->constrained()->cascadeOnDelete();

            // SM-2 core fields
            $table->unsignedSmallInteger('repetitions')->default(0);
            $table->decimal('ease_factor', 4, 2)->default(2.50);
            $table->unsignedSmallInteger('interval_days')->default(1);
            $table->timestamp('next_review_at')->nullable();
            $table->timestamp('last_reviewed_at')->nullable();
            $table->tinyInteger('last_quality')->nullable();

            // Métricas acumuladas para análise
            $table->unsignedSmallInteger('total_reviews')->default(0);
            $table->decimal('cumulative_score', 6, 2)->default(0);

            $table->timestamps();

            $table->unique(['user_id', 'phrase_id']);
            $table->index(['user_id', 'next_review_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phrase_review_states');
    }
};
