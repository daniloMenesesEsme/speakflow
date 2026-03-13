<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Representa uma sessão de conversa entre o usuário e o app.
     * Cada sessão pertence a um diálogo pré-cadastrado e armazena o estado
     * atual da conversa para retomada offline.
     */
    public function up(): void
    {
        Schema::create('conversation_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dialogue_id')->constrained()->cascadeOnDelete();

            $table->string('topic_slug', 100);
            $table->string('status', 20)->default('active');
            // status: active | completed | abandoned | paused

            $table->unsignedSmallInteger('current_line_order')->default(0);
            $table->unsignedSmallInteger('total_lines')->default(0);
            $table->unsignedSmallInteger('user_turns_total')->default(0);
            $table->unsignedSmallInteger('user_turns_correct')->default(0);
            $table->unsignedSmallInteger('total_score')->default(0);
            $table->unsignedSmallInteger('messages_count')->default(0);
            $table->unsignedSmallInteger('xp_earned')->default(0);

            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'topic_slug']);
            $table->index('topic_slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_sessions');
    }
};
