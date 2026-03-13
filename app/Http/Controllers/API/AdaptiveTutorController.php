<?php

namespace App\Http\Controllers\API;

use App\Services\AdaptiveTutorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdaptiveTutorController extends BaseController
{
    public function __construct(private AdaptiveTutorService $tutor)
    {
    }

    /**
     * GET /api/v1/ai/adaptive-plan
     *
     * Retorna o plano de estudo adaptativo completo do usuário.
     * Inclui nível calculado, foco gramatical, tópicos recomendados,
     * exercícios pendentes e insights semanais.
     *
     * Query params opcionais:
     *   ?section=level|grammar|topics|exercises|weekly
     */
    public function plan(Request $request): JsonResponse
    {
        $user    = auth()->user();
        $section = $request->query('section');

        $allowed = ['level', 'grammar', 'topics', 'exercises', 'weekly'];

        if ($section && in_array($section, $allowed)) {
            $data = match ($section) {
                'level'     => ['level'     => $this->tutor->getUserLevel($user)],
                'grammar'   => ['grammar_focus' => $this->tutor->getGrammarFocus($user)],
                'topics'    => ['recommended_topics' => $this->tutor->getRecommendedTopics($user)],
                'exercises' => ['recommended_exercises' => $this->tutor->getRecommendedExercises($user)],
                'weekly'    => ['weekly_insight' => $this->tutor->getWeeklyInsight($user)],
            };

            return $this->success($data, "Seção '{$section}' do plano adaptativo.");
        }

        $plan = $this->tutor->getAdaptivePlan($user);

        return $this->success($plan, 'Plano de estudo adaptativo.');
    }

    /**
     * GET /api/v1/ai/adaptive-plan/level
     *
     * Retorna apenas o nível adaptativo do usuário.
     */
    public function level(): JsonResponse
    {
        $data = $this->tutor->getUserLevel(auth()->user());

        return $this->success($data, 'Nível adaptativo do usuário.');
    }

    /**
     * GET /api/v1/ai/adaptive-plan/grammar
     *
     * Retorna foco gramatical: erros mais frequentes + sugestões.
     */
    public function grammar(): JsonResponse
    {
        $data = $this->tutor->getGrammarFocus(auth()->user());

        return $this->success($data, 'Foco gramatical personalizado.');
    }

    /**
     * GET /api/v1/ai/adaptive-plan/topics
     *
     * Retorna tópicos de conversa recomendados para o nível do usuário.
     */
    public function topics(): JsonResponse
    {
        $data = $this->tutor->getRecommendedTopics(auth()->user());

        return $this->success($data, 'Tópicos recomendados para praticar.');
    }

    /**
     * GET /api/v1/ai/adaptive-plan/exercises
     *
     * Retorna exercícios pendentes e sugestão de próxima lição.
     */
    public function exercises(): JsonResponse
    {
        $data = $this->tutor->getRecommendedExercises(auth()->user());

        return $this->success($data, 'Exercícios recomendados.');
    }
}
