<?php

namespace App\Http\Controllers\API;

use App\Services\LearningAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LearningAnalyticsController extends BaseController
{
    public function __construct(private LearningAnalyticsService $analytics)
    {
    }

    /**
     * GET /api/v1/analytics
     *
     * Retorna relatório consolidado de aprendizado do usuário autenticado.
     * Agrega dados de XP, exercícios, lições, correções gramaticais,
     * tópicos mais praticados e consumo de IA.
     *
     * Query params:
     *   ?section=general|learning|topics|ai_usage  (opcional — retorna só a seção)
     */
    public function index(Request $request): JsonResponse
    {
        $user    = auth()->user();
        $section = $request->query('section');

        $allowed = ['general', 'learning', 'topics', 'ai_usage'];

        // Seção específica solicitada via query param
        if ($section && in_array($section, $allowed)) {
            $data = match ($section) {
                'general'  => $this->analytics->getGeneralStats($user),
                'learning' => $this->analytics->getLearningStats($user),
                'topics'   => $this->analytics->getTopicStats($user),
                'ai_usage' => $this->analytics->getAiUsageStats($user),
            };

            return $this->success($data, "Estatísticas de '{$section}'.");
        }

        // Relatório completo (padrão)
        $report = $this->analytics->getFullReport($user);

        return $this->success($report, 'Relatório de aprendizado gerado com sucesso.');
    }

    /**
     * GET /api/v1/analytics/general
     *
     * Atalho para estatísticas gerais (XP, lições, exercícios, conversas).
     */
    public function general(): JsonResponse
    {
        return $this->success(
            $this->analytics->getGeneralStats(auth()->user()),
            'Estatísticas gerais do aluno.'
        );
    }

    /**
     * GET /api/v1/analytics/learning
     *
     * Atalho para análise de erros gramaticais e tendência de aprendizado.
     */
    public function learning(): JsonResponse
    {
        return $this->success(
            $this->analytics->getLearningStats(auth()->user()),
            'Estatísticas de erros gramaticais.'
        );
    }

    /**
     * GET /api/v1/analytics/topics
     *
     * Atalho para estatísticas de tópicos mais praticados.
     */
    public function topics(): JsonResponse
    {
        return $this->success(
            $this->analytics->getTopicStats(auth()->user()),
            'Tópicos mais praticados.'
        );
    }

    /**
     * GET /api/v1/analytics/ai-usage
     *
     * Atalho para consumo de tokens e custo estimado da IA.
     */
    public function aiUsage(): JsonResponse
    {
        return $this->success(
            $this->analytics->getAiUsageStats(auth()->user()),
            'Estatísticas de uso de IA.'
        );
    }
}
