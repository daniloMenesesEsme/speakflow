<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dialogues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->string('topic', 200);
            $table->string('level', 5)->default('A1'); // CEFR: A1, A2, B1, B2, C1, C2
            $table->text('description')->nullable();
            $table->string('context')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['language_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dialogues');
    }
};
