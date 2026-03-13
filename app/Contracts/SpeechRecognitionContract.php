<?php

namespace App\Contracts;

use App\ValueObjects\PronunciationResult;

/**
 * Contrato que todo driver de reconhecimento de voz deve implementar.
 *
 * Permite trocar o motor de análise (Mock → Azure → Google → Whisper)
 * sem alterar nenhum código do PronunciationAnalyzer ou dos controllers.
 *
 * Implementações previstas:
 *   - MockSpeechRecognitionDriver   → padrão offline, baseado em texto
 *   - AzureSpeechDriver             → Azure Cognitive Services Speech
 *   - GoogleSpeechDriver            → Google Cloud Speech-to-Text
 *   - WhisperDriver                 → OpenAI Whisper (local ou API)
 */
interface SpeechRecognitionContract
{
    /**
     * Nome único do driver, usado para persistência e logs.
     * Exemplos: 'mock', 'azure', 'google', 'whisper'
     */
    public function driverName(): string;

    /**
     * Analisa o áudio e retorna um PronunciationResult com as três métricas.
     *
     * @param  string  $audioPath      Caminho local ou URL do arquivo de áudio.
     * @param  string  $expectedText   Texto que o usuário deveria pronunciar.
     * @param  array   $options        Opções adicionais (idioma, sensibilidade, etc.)
     */
    public function analyze(
        string $audioPath,
        string $expectedText,
        array $options = []
    ): PronunciationResult;

    /**
     * Verifica se o driver está disponível e configurado.
     * Usado para fallback automático quando um driver externo falha.
     */
    public function isAvailable(): bool;
}
