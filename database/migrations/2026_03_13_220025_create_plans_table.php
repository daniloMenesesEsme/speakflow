<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();

            $table->string('name', 60);          // Free, Pro, Premium
            $table->string('slug', 60)->unique(); // free, pro, premium

            // Preço em reais (0.00 para Free)
            $table->decimal('price', 8, 2)->default(0);

            // monthly | yearly | lifetime
            $table->string('billing_cycle', 20)->default('monthly');

            // Limites diários e mensais — armazenados como JSON
            // Exemplo: {"ai_messages_per_day":5,"voice_messages_per_day":2,"unlimited":false}
            $table->json('features');

            // Destaque visual no app (ex: plano recomendado)
            $table->boolean('is_featured')->default(false);

            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index('slug');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
