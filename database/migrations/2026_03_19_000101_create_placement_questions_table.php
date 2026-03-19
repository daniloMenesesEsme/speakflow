<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('placement_questions', function (Blueprint $table) {
            $table->id();
            $table->text('question');
            $table->json('options')->nullable();
            $table->string('correct_answer');
            $table->string('skill', 20); // grammar, vocabulary, reading
            $table->string('cefr_level', 2); // A1..C2
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->decimal('weight', 5, 2)->default(1.0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'cefr_level']);
            $table->index(['is_active', 'skill']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('placement_questions');
    }
};

