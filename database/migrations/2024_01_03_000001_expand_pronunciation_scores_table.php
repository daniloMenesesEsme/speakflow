<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expande a tabela pronunciation_scores para suportar as três métricas
     * independentes de pronúncia: accuracy, fluency e confidence.
     *
     * Também adiciona campos para rastreabilidade do driver de reconhecimento
     * de voz, permitindo integração futura com serviços externos.
     */
    public function up(): void
    {
        Schema::table('pronunciation_scores', function (Blueprint $table) {

            // ─── Três métricas principais ────────────────────────────────
            $table->decimal('accuracy', 5, 2)->default(0)
                ->after('score')
                ->comment('Precisão fonética (0–100): quão corretamente os sons foram produzidos');

            $table->decimal('fluency', 5, 2)->default(0)
                ->after('accuracy')
                ->comment('Fluência (0–100): ritmo, pausas, velocidade e prosódia da fala');

            $table->decimal('confidence', 5, 2)->default(0)
                ->after('fluency')
                ->comment('Confiança/clareza (0–100): volume, nitidez e naturalidade da voz');

            // ─── Dados do reconhecimento ─────────────────────────────────
            $table->text('transcription')->nullable()
                ->after('confidence')
                ->comment('O que o motor de voz entendeu que o usuário disse');

            $table->string('driver', 50)->default('mock')
                ->after('transcription')
                ->comment('Driver usado: mock | azure | google | whisper');

            $table->unsignedSmallInteger('processing_time_ms')->nullable()
                ->after('driver')
                ->comment('Tempo de processamento em milissegundos');

            // ─── Índice para relatórios por métrica ──────────────────────
            $table->index(['user_id', 'accuracy']);
            $table->index(['user_id', 'fluency']);
        });
    }

    public function down(): void
    {
        Schema::table('pronunciation_scores', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'accuracy']);
            $table->dropIndex(['user_id', 'fluency']);
            $table->dropColumn([
                'accuracy', 'fluency', 'confidence',
                'transcription', 'driver', 'processing_time_ms',
            ]);
        });
    }
};
