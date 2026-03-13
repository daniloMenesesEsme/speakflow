<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phrases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->text('english_text');
            $table->text('portuguese_text');
            $table->string('audio_path')->nullable();
            $table->string('difficulty', 20)->default('easy');
            $table->string('phonetic', 300)->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();

            $table->index(['lesson_id', 'difficulty']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phrases');
    }
};
