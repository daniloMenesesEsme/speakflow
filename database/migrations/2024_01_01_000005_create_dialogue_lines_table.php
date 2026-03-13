<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dialogue_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dialogue_id')->constrained()->cascadeOnDelete();
            $table->string('speaker', 50);
            $table->text('text');
            $table->text('expected_answer')->nullable();
            $table->text('translation')->nullable();
            $table->string('audio_path')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_user_turn')->default(false);
            $table->json('hints')->nullable();
            $table->timestamps();

            $table->index(['dialogue_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dialogue_lines');
    }
};
