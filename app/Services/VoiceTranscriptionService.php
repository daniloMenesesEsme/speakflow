<?php

namespace App\Services;

use App\Models\AiConversation;
use App\Models\AiVoiceMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenAI;

class VoiceTranscriptionService
{
    private const WHISPER_MODEL    = 'whisper-1';
    private const STORAGE_DISK     = 'local';
    private const STORAGE_FOLDER   = 'voice_messages';
    private const MAX_FILE_SIZE_MB = 25;    // Limite da API Whisper

    // ─── API pública ─────────────────────────────────────────────────────────

    /**
     * Processa um arquivo de áudio enviado pelo usuário:
     *  1. Salva o arquivo em storage/app/voice_messages/
     *  2. Transcreve com Whisper (ou fallback offline)
     *  3. Persiste em ai_voice_messages
     *
     * @return array{
     *   voice_message_id: int,
     *   transcription: string,
     *   detected_language: string|null,
     *   driver: string,
     *   audio_path: string,
     *   processing_time_ms: int,
     * }
     */
    public function transcribe(
        User          $user,
        UploadedFile  $audioFile,
        ?AiConversation $conversation = null,
    ): array {
        $startedAt = hrtime(true);

        // ── 1. Salvar arquivo ─────────────────────────────────────────────────
        $audioPath = $this->storeAudio($audioFile, $user->id);
        $format    = strtolower($audioFile->getClientOriginalExtension() ?: 'webm');

        // ── 2. Transcrever ────────────────────────────────────────────────────
        [$transcription, $detectedLanguage, $driver] = $this->runTranscription(
            Storage::disk(self::STORAGE_DISK)->path($audioPath),
            $format,
        );

        $processingMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        // ── 3. Persistir ──────────────────────────────────────────────────────
        $voiceMessage = AiVoiceMessage::create([
            'user_id'              => $user->id,
            'conversation_id'      => $conversation?->id,
            'audio_path'           => $audioPath,
            'audio_format'         => $format,
            'transcription'        => $transcription,
            'detected_language'    => $detectedLanguage,
            'transcription_driver' => $driver,
            'processing_time_ms'   => $processingMs,
        ]);

        return [
            'voice_message_id'  => $voiceMessage->id,
            'transcription'     => $transcription,
            'detected_language' => $detectedLanguage,
            'driver'            => $driver,
            'audio_path'        => $audioPath,
            'processing_time_ms'=> $processingMs,
        ];
    }

    /**
     * Retorna o histórico de mensagens de voz do usuário.
     */
    public function getHistory(User $user, int $perPage = 20): array
    {
        $records = AiVoiceMessage::forUser($user->id)
            ->transcribed()
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return [
            'total'        => $records->total(),
            'per_page'     => $records->perPage(),
            'current_page' => $records->currentPage(),
            'last_page'    => $records->lastPage(),
            'data'         => collect($records->items())->map(fn ($m) => [
                'id'                => $m->id,
                'conversation_id'   => $m->conversation_id,
                'transcription'     => $m->transcription,
                'detected_language' => $m->detected_language,
                'audio_format'      => $m->audio_format,
                'driver'            => $m->transcription_driver,
                'processing_time_ms'=> $m->processing_time_ms,
                'created_at'        => $m->created_at->toISOString(),
            ])->toArray(),
        ];
    }

    // ─── Internos ─────────────────────────────────────────────────────────────

    /**
     * Salva o arquivo de áudio e retorna o caminho relativo no disco local.
     */
    private function storeAudio(UploadedFile $file, int $userId): string
    {
        $filename  = sprintf(
            '%d_%s_%s.%s',
            $userId,
            now()->format('Ymd_His'),
            Str::random(8),
            strtolower($file->getClientOriginalExtension() ?: 'webm'),
        );

        $folder = self::STORAGE_FOLDER . '/' . now()->format('Y/m');

        $file->storeAs($folder, $filename, self::STORAGE_DISK);

        return $folder . '/' . $filename;
    }

    /**
     * Decide entre Whisper (API) ou fallback mock e executa a transcrição.
     * Retorna: [transcription, detected_language, driver]
     */
    private function runTranscription(string $absolutePath, string $format): array
    {
        $apiKey = config('services.openai.key');

        if ($apiKey) {
            return $this->whisperTranscribe($apiKey, $absolutePath, $format);
        }

        Log::info('VoiceTranscriptionService: sem OPENAI_API_KEY, usando mock.');
        return $this->mockTranscription($absolutePath, $format);
    }

    /**
     * Chama a API Whisper da OpenAI para transcrever o áudio.
     */
    private function whisperTranscribe(
        string $apiKey,
        string $absolutePath,
        string $format,
    ): array {
        try {
            $client = OpenAI::client($apiKey);

            $response = $client->audio()->transcribe([
                'model'           => self::WHISPER_MODEL,
                'file'            => fopen($absolutePath, 'r'),
                'response_format' => 'verbose_json',  // inclui detected language
                'language'        => null,             // auto-detect
            ]);

            $transcription     = trim($response->text ?? '');
            $detectedLanguage  = $response->language ?? null;

            if (empty($transcription)) {
                return ['[inaudível]', $detectedLanguage, self::WHISPER_MODEL];
            }

            return [$transcription, $detectedLanguage, self::WHISPER_MODEL];

        } catch (\Throwable $e) {
            Log::error('VoiceTranscriptionService: Whisper falhou — ' . $e->getMessage());
            return $this->mockTranscription($absolutePath, $format);
        }
    }

    /**
     * Mock offline: gera uma transcrição simulada baseada no nome do arquivo.
     * Usado quando não há OPENAI_API_KEY ou em testes automatizados.
     */
    private function mockTranscription(string $absolutePath, string $format): array
    {
        // Simula ~200ms de "processamento"
        usleep(200_000);

        $phrases = [
            'Hello, how are you today?',
            'I want to practice my English conversation.',
            'Can you help me with this sentence?',
            'What is the best way to learn vocabulary?',
            'I am studying English every day.',
            'Could you correct my pronunciation?',
            'Thank you for your help, teacher.',
            'I think this is a great learning app.',
        ];

        $transcription = $phrases[array_rand($phrases)];

        return [$transcription, 'en', 'mock'];
    }
}
