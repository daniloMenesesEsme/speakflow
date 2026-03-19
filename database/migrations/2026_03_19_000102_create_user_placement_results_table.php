<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_placement_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('total_questions');
            $table->unsignedInteger('correct_answers');
            $table->decimal('score_percentage', 5, 2);
            $table->string('level', 2); // A1..C2
            $table->json('skill_breakdown'); // { grammar: {...}, ... }
            $table->json('answers')->nullable(); // respostas do usuário
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_placement_results');
    }
};

