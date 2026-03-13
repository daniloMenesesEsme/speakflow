<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pronunciation_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('phrase_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 5, 2);
            $table->string('audio_path')->nullable();
            $table->json('phoneme_scores')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'phrase_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pronunciation_scores');
    }
};
