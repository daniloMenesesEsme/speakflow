<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->text('question');
            $table->text('correct_answer');
            $table->json('options')->nullable();
            $table->text('explanation')->nullable();
            $table->string('difficulty', 20)->default('easy');
            $table->integer('order')->default(0);
            $table->integer('points')->default(10);
            $table->timestamps();

            $table->index(['lesson_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
