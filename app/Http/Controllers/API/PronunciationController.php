<?php

namespace App\Http\Controllers\API;

use App\Models\Phrase;
use App\Models\PronunciationScore;
use App\Services\PronunciationAnalyzer;
use App\Services\AchievementService;
use App\ValueObjects\PronunciationResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PronunciationController extends BaseController
{
    public function __construct(
        private readonly PronunciationAnalyzer $analyzer,
        private readonly AchievementService $achievementService
    ) {}

    // ══════════════════════════════════════════════════════════════════════════
    //  POST /pronunciation/analyze
    //  Ponto de entrada principal — orquestra análise + persistência
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Analisa a pronúncia e registra o score automaticamente.
     *
     * Body:
     *   phrase_id       int      obrigatório
     *   audio_path      string   opcional (caminho local do áudio)
     *   transcription   string   opcional (texto detectado pelo STT do device)
     *   stt_confidence  float    opcional (confiança do STT nativo, 0–1)
     *   audio_duration  float    opcional (duração em segundos)
     */
    public function analyze(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phrase_id'      => 'required|integer|exists:phrases,id',
            'audio_path'     => 'nullable|string|max:500',
            'transcription'  => 'nullable|string|max:1000',
            'stt_confidence' => 'nullable|numeric|min:0|max:1',
            'audio_duration' => 'nullable|numeric|min:0|max:600',
        ]);

        if ($validator->fails()) {
            return $this->error('Dados inválidos.', $validator->errors(), 422);
        }

        $user   = auth()->user();
        $phrase = Phrase::findOrFail($request->phrase_id);

        $audioPath = $request->audio_path ?? '';
        $options   = array_filter([
            'transcription'  => $request->transcription,
            'stt_confidence' => $request->stt_confidence,
            'audio_duration' => $request->audio_duration,
        ], fn ($v) => $v !== null);

        // 1. analyzePronunciation() → retorna resultado com as 3 métricas
        $analysis = $this->analyzer->analyzePronunciation(
            $audioPath,
            $phrase->english_text,
            $options
        );

        /** @var PronunciationResult $result */
        $result = $analysis['raw_result'];

        // 2. savePronunciationScore() → persiste no banco
        $saved = $this->analyzer->savePronunciationScore($user, $phrase, $result);

        // 3. Conquistas desbloqueadas
        $newAchievements = $this->achievementService->checkAndAwardAchievements($user->fresh());

        return $this->created([
            'score' => [
                'id'             => $saved->id,
                'composite_score'=> $saved->score,
                'accuracy'       => $saved->accuracy,
                'fluency'        => $saved->fluency,
                'confidence'     => $saved->confidence,
                'grade'          => $saved->grade,
                'accuracy_label' => $saved->accuracy_label,
                'fluency_label'  => $saved->fluency_label,
                'confidence_label'=> $saved->confidence_label,
                'weakest_metric' => $saved->weakest_metric,
                'transcription'  => $saved->transcription,
                'driver'         => $saved->driver,
                'processing_ms'  => $saved->processing_time_ms,
                'created_at'     => $saved->created_at->toISOString(),
            ],
            'feedback' => [
                'summary'        => $analysis['feedback']['summary'],
                'tips'           => $analysis['feedback']['tips'],
                'encouragement'  => $analysis['feedback']['encouragement'],
                'metrics_labels' => $analysis['feedback']['metrics_labels'],
            ],
            'phrase_progress'  => $this->analyzer->getPhraseProgress($user, $phrase),
            'new_achievements' => collect($newAchievements)->map(fn ($a) => [
                'id'    => $a->id,
                'title' => $a->title,
                'icon'  => $a->icon ?? null,
            ]),
        ], 'Análise de pronúncia concluída.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  POST /pronunciation/calculate-score
    //  Calcula score composto a partir de métricas fornecidas pelo app
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Calcula o score composto sem persistir — útil para preview no app.
     *
     * Body:
     *   accuracy    float   obrigatório (0–100)
     *   fluency     float   obrigatório (0–100)
     *   confidence  float   obrigatório (0–100)
     */
    public function calculateScore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'accuracy'   => 'required|numeric|min:0|max:100',
            'fluency'    => 'required|numeric|min:0|max:100',
            'confidence' => 'required|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return $this->error('Dados inválidos.', $validator->errors(), 422);
        }

        $calculation = $this->analyzer->calculatePronunciationScore(
            (float) $request->accuracy,
            (float) $request->fluency,
            (float) $request->confidence
        );

        return $this->success($calculation, 'Score calculado.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  POST /pronunciation/score  (legado — mantido por compatibilidade)
    //  Aceita score direto sem análise de áudio
    // ══════════════════════════════════════════════════════════════════════════

    public function score(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phrase_id'      => 'required|integer|exists:phrases,id',
            'score'          => 'required|numeric|min:0|max:100',
            'accuracy'       => 'nullable|numeric|min:0|max:100',
            'fluency'        => 'nullable|numeric|min:0|max:100',
            'confidence'     => 'nullable|numeric|min:0|max:100',
            'audio_path'     => 'nullable|string|max:500',
            'phoneme_scores' => 'nullable|array',
            'transcription'  => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->error('Dados inválidos.', $validator->errors(), 422);
        }

        $user      = auth()->user();
        $phrase    = Phrase::findOrFail($request->phrase_id);
        $composite = (float) $request->score;

        // Quando as métricas individuais não são fornecidas, distribui o score composto
        $accuracy   = $request->filled('accuracy')   ? (float) $request->accuracy   : $composite;
        $fluency    = $request->filled('fluency')    ? (float) $request->fluency    : $composite;
        $confidence = $request->filled('confidence') ? (float) $request->confidence : $composite;

        $result = new PronunciationResult(
            accuracy:      $accuracy,
            fluency:       $fluency,
            confidence:    $confidence,
            transcription: $request->transcription ?? '',
            driver:        'manual',
            phonemeScores: $request->phoneme_scores ?? [],
        );

        $feedback = $this->analyzer->generateDetailedFeedback($result, $phrase->english_text);
        $saved    = $this->analyzer->savePronunciationScore($user, $phrase, $result);

        $newAchievements = $this->achievementService->checkAndAwardAchievements($user->fresh());

        return $this->created([
            'score' => [
                'id'              => $saved->id,
                'composite_score' => $saved->score,
                'accuracy'        => $saved->accuracy,
                'fluency'         => $saved->fluency,
                'confidence'      => $saved->confidence,
                'grade'           => $saved->grade,
                'feedback'        => $saved->feedback,
                'created_at'      => $saved->created_at->toISOString(),
            ],
            'feedback'         => $feedback,
            'phrase_progress'  => $this->analyzer->getPhraseProgress($user, $phrase),
            'new_achievements' => collect($newAchievements)->map(fn ($a) => [
                'id'    => $a->id,
                'title' => $a->title,
            ]),
        ], 'Pontuação de pronúncia registrada.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  GET /pronunciation/analysis
    // ══════════════════════════════════════════════════════════════════════════

    public function analysis(): JsonResponse
    {
        $user     = auth()->user();
        $analysis = $this->analyzer->analyzeUserPronunciation($user);

        return $this->success($analysis, 'Análise de pronúncia gerada.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  GET /pronunciation/phrases/{phrase}/progress
    // ══════════════════════════════════════════════════════════════════════════

    public function phraseProgress(Phrase $phrase): JsonResponse
    {
        $user     = auth()->user();
        $progress = $this->analyzer->getPhraseProgress($user, $phrase);

        return $this->success($progress);
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  GET /pronunciation/weekly-report
    // ══════════════════════════════════════════════════════════════════════════

    public function weeklyReport(): JsonResponse
    {
        $user   = auth()->user();
        $report = $this->analyzer->getWeeklyReport($user);

        return $this->success($report, 'Relatório semanal de pronúncia.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  GET /pronunciation/phrases
    // ══════════════════════════════════════════════════════════════════════════

    public function phrasesForPractice(Request $request): JsonResponse
    {
        $user  = auth()->user();
        $limit = min((int) $request->get('limit', 10), 50);

        $phrases = Phrase::whereHas('lesson', function ($q) use ($user) {
            $q->whereHas('language', fn ($lq) => $lq->where('code', $user->target_language))
              ->where('level', $user->level)
              ->active();
        })
            ->with('lesson')
            ->inRandomOrder()
            ->limit($limit)
            ->get();

        return $this->success($phrases->map(fn ($p) => [
            'id'              => $p->id,
            'english_text'    => $p->english_text,
            'portuguese_text' => $p->portuguese_text,
            'audio_path'      => $p->audio_path,
            'phonetic'        => $p->phonetic ?? null,
            'difficulty'      => $p->difficulty,
            'lesson'          => $p->lesson ? ['id' => $p->lesson->id, 'title' => $p->lesson->title] : null,
        ]));
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  GET /pronunciation/history
    // ══════════════════════════════════════════════════════════════════════════

    public function history(Request $request): JsonResponse
    {
        $user    = auth()->user();
        $perPage = min((int) $request->get('per_page', 20), 100);

        $scores = PronunciationScore::forUser($user->id)
            ->with('phrase:id,english_text')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->paginated($scores->through(fn ($s) => [
            'id'              => $s->id,
            'phrase'          => $s->phrase->english_text ?? '',
            'composite_score' => $s->score,
            'accuracy'        => $s->accuracy,
            'fluency'         => $s->fluency,
            'confidence'      => $s->confidence,
            'grade'           => $s->grade,
            'weakest_metric'  => $s->weakest_metric,
            'feedback'        => $s->feedback,
            'driver'          => $s->driver,
            'created_at'      => $s->created_at->toISOString(),
        ]), 'Histórico de pronúncia.');
    }
}
