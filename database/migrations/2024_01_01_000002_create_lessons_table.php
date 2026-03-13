<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('level', 5)->default('A1'); // CEFR: A1, A2, B1, B2, C1, C2
            $table->string('category', 100);
            $table->integer('order')->default(0);
            $table->text('description')->nullable();
            $table->string('thumbnail')->nullable();
            $table->integer('xp_reward')->default(10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['language_id', 'level', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
