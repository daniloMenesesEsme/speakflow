<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Tipo de recurso consumido
            // ai_message | lesson_generation | voice_message
            $table->string('type', 40);

            // Quantidade consumida (geralmente 1, mas pode ser > 1 para voz)
            $table->unsignedSmallInteger('quantity')->default(1);

            // Metadados opcionais (ex: lesson_id, conversation_id)
            $table->json('metadata')->nullable();

            // Plano ativo no momento do uso (snapshot para auditoria)
            $table->string('plan_slug', 60)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'type', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_logs');
    }
};
