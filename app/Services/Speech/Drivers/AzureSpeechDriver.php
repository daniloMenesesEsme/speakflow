<?php

namespace App\Services\Speech\Drivers;

use App\Contracts\SpeechRecognitionContract;
use App\ValueObjects\PronunciationResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Driver para Azure Cognitive Services Speech — Pronunciation Assessment.
 *
 * Documentação: https://learn.microsoft.com/azure/cognitive-services/speech-service/
 *
 * Configuração necessária em config/services.php:
 *   'azure_speech' => [
 *       'key'    => env('AZURE_SPEECH_KEY'),
 *       'region' => env('AZURE_SPEECH_REGION', 'eastus'),
 *   ]
 *
 * Este arquivo serve como STUB — o corpo dos métodos privados deve ser
 * preenchido quando a integração for ativada.
 */
class AzureSpeechDriver implements SpeechRecognitionContract
{
    private string $apiKey;
    private string $region;
    private string $endpoint;

    public function __construct()
    {
        $this->apiKey   = config('services.azure_speech.key', '');
        $this->region   = config('services.azure_speech.region', 'eastus');
        $this->endpoint = "https://{$this->region}.stt.speech.microsoft.com/speech/recognition/conversation/cognitiveservices/v1";
    }

    public function driverName(): string
    {
        return 'azure';
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    public function analyze(
        string $audioPath,
        string $expectedText,
        array $options = []
    ): PronunciationResult {
        if (!$this->isAvailable()) {
            return PronunciationResult::failed($this->driverName(), 'Azure key not configured.');
        }

        try {
            $startMs  = (int) round(microtime(true) * 1000);
            $response = $this->callAzureApi($audioPath, $expectedText, $options);
            $processingMs = (int) round(microtime(true) * 1000) - $startMs;

            return $this->mapResponse($response, $processingMs);

        } catch (\Throwable $e) {
            Log::error('AzureSpeechDriver failed', ['error' => $e->getMessage()]);
            return PronunciationResult::failed($this->driverName(), $e->getMessage());
        }
    }

    // ─── Stub — implementar ao ativar a integração ───────────────────────────

    private function callAzureApi(string $audioPath, string $expectedText, array $options): array
    {
        // TODO: Implementar chamada à API Azure Speech
        // Referência: https://learn.microsoft.com/azure/ai-services/speech-service/rest-speech-to-text
        throw new \RuntimeException('AzureSpeechDriver: callAzureApi() não implementado.');
    }

    private function mapResponse(array $response, int $processingMs): PronunciationResult
    {
        // TODO: Mapear resposta da Azure para PronunciationResult
        // Campos esperados: AccuracyScore, FluencyScore, CompletenessScore, PronScore
        throw new \RuntimeException('AzureSpeechDriver: mapResponse() não implementado.');
    }
}
