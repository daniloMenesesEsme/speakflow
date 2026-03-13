<?php

namespace App\Http\Controllers\API;

use App\Models\Phrase;
use App\Services\LearningEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Expõe os endpoints do LearningEngine:
 *  - Progresso completo do usuário
 *  - Estimativa de tempo para o próximo nível
 *  - Seleção da próxima lição
 *  - Frases para revisão (SRS)
 *  - Processamento de revisão individual (SM-2)
 */
class LearningController extends BaseController
{
    public function __construct(private LearningEngine $engine) {}

    /**
     * GET /api/v1/learning/progress
     * Snapshot completo do progresso do usuário com CEFR, XP, streak e SRS.
     */
    public function progress(): JsonResponse
    {
        $progress = $this->engine->calculateUserProgress(auth()->user());

        return $this->success($progress, 'Progresso calculado com sucesso.');
    }

    /**
     * GET /api/v1/learning/next-level-estimate
     * Estimativa de dias, sessões e minutos para atingir o próximo nível.
     */
    public function nextLevelEstimate(): JsonResponse
    {
        $estimate = $this->engine->calculateNextLevelEstimate(auth()->user());

        return $this->success($estimate, 'Estimativa calculada com sucesso.');
    }

    /**
     * GET /api/v1/learning/next-lesson
     * Retorna a próxima lição ideal para o usuário (em andamento, nova ou revisão).
     */
    public function nextLesson(): JsonResponse
    {
        $result = $this->engine->selectNextLesson(auth()->user());

        if ($result === null) {
            return $this->success(null, 'Nenhuma lição disponível no momento.');
        }

        return $this->success($result, 'Próxima lição selecionada.');
    }

    /**
     * GET /api/v1/learning/phrases-for-review
     * Lista de frases para revisão baseada no algoritmo SM-2.
     * Query param: limit (default 20)
     */
    public function phrasesForReview(Request $request): JsonResponse
    {
        $limit   = (int) $request->get('limit', 20);
        $limit   = max(1, min(50, $limit));

        $phrases = $this->engine->selectPhrasesForReview(auth()->user(), $limit);

        return $this->success([
            'phrases' => $phrases,
            'total'   => $phrases->count(),
        ], 'Frases para revisão selecionadas pelo algoritmo SM-2.');
    }

    /**
     * POST /api/v1/learning/phrases/{phrase}/review
     * Processa a avaliação de uma frase e atualiza o estado SM-2.
     *
     * Body:
     *   quality (int 0–5): qualidade da resposta do usuário
     *     5 = perfeito, 4 = bom, 3 = aceitável, 2-0 = falha
     */
    public function processReview(Request $request, Phrase $phrase): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'quality' => 'required|integer|min:0|max:5',
        ]);

        if ($validator->fails()) {
            return $this->error('Dados inválidos.', $validator->errors(), 422);
        }

        $state = $this->engine->processReview(
            auth()->user(),
            $phrase,
            (int) $request->quality
        );

        return $this->success([
            'phrase_id'        => $phrase->id,
            'quality_given'    => $request->quality,
            'mastery_level'    => $state->mastery_level,
            'repetitions'      => $state->repetitions,
            'ease_factor'      => (float) $state->ease_factor,
            'interval_days'    => $state->interval_days,
            'next_review_at'   => $state->next_review_at->toISOString(),
            'days_until_review'=> $state->days_until_review,
            'feedback'         => $this->qualityFeedback((int) $request->quality),
        ], 'Revisão processada.');
    }

    /**
     * GET /api/v1/learning/cefr-levels
     * Lista todos os níveis CEFR com thresholds e descrições.
     */
    public function cefrLevels(): JsonResponse
    {
        $user  = auth()->user();
        $codes = \App\ValueObjects\CefrLevel::allCodes();

        $levels = array_map(function (string $code) use ($user) {
            $level = \App\ValueObjects\CefrLevel::fromCode($code);

            return [
                'code'                => $level->code(),
                'description'         => $level->description(),
                'rank'                => $level->numericRank(),
                'min_xp'              => $level->minXp(),
                'max_xp'              => $level->maxXp(),
                'is_current'          => $user->level === $code,
                'is_completed'        => $user->total_xp >= ($level->maxXp() ?? PHP_INT_MAX),
            ];
        }, $codes);

        return $this->success($levels, 'Níveis CEFR.');
    }

    private function qualityFeedback(int $quality): string
    {
        return match ($quality) {
            5 => 'Perfeito! Intervalo máximo aplicado.',
            4 => 'Muito bom! Próxima revisão em breve.',
            3 => 'Correto. Continue praticando para consolidar.',
            2 => 'Quase lá. Esta frase será revisada em breve.',
            1 => 'Errado. Você verá esta frase novamente hoje.',
            0 => 'Falha total. Revise esta frase com atenção.',
            default => '',
        };
    }
}
