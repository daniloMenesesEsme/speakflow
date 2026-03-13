<?php

namespace App\Http\Controllers\API;

use App\Models\GeneratedLesson;
use App\Services\AdaptiveTutorService;
use App\Services\AiLessonGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiLessonController extends BaseController
{
    public function __construct(
        private AiLessonGeneratorService $generator,
        private AdaptiveTutorService     $adaptiveTutor,
    ) {
    }

    /**
     * POST /api/v1/ai/generate-lesson
     *
     * Gera uma lição dinâmica com exercícios baseados no nível e foco gramatical do usuário.
     * Se nenhum parâmetro for informado no body, consulta o AdaptiveTutorService automaticamente.
     *
     * Body (todos opcionais):
     * {
     *   "level":         "A2",                        // se omitido → calcula pelo AdaptiveTutor
     *   "topic":         "Food and Restaurant",        // se omitido → usa tópico recomendado
     *   "grammar_focus": ["want to + infinitive"]      // se omitido → usa foco gramatical detectado
     * }
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'level'         => 'nullable|string|in:A1,A2,B1,B2,C1,C2',
            'topic'         => 'nullable|string|min:2|max:150',
            'grammar_focus' => 'nullable|array',
            'grammar_focus.*' => 'string|max:200',
        ]);

        $user = auth()->user();

        // ── Resolver parâmetros: usar body ou AdaptiveTutor automaticamente ──────
        [$level, $topic, $grammarFocus] = $this->resolveParameters(
            $user,
            $request->input('level'),
            $request->input('topic'),
            $request->input('grammar_focus'),
        );

        // ── Gerar lição ───────────────────────────────────────────────────────────
        $lesson = $this->generator->generateLesson($user, $level, $topic, $grammarFocus);

        $message = $lesson['driver'] === 'openai'
            ? 'Lição gerada pela IA com sucesso!'
            : 'Lição gerada com conteúdo offline.';

        return $this->success(
            array_merge($lesson, [
                'adaptive_params' => [
                    'level'         => $level,
                    'topic'         => $topic,
                    'grammar_focus' => $grammarFocus,
                    'source'        => $request->has('level') ? 'user_provided' : 'adaptive_tutor',
                ],
            ]),
            $message,
            201
        );
    }

    /**
     * GET /api/v1/ai/lessons
     *
     * Lista o histórico de lições geradas para o usuário autenticado, paginado.
     */
    public function history(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 10), 30);
        $data    = $this->generator->getHistory(auth()->user(), $perPage);

        return $this->success($data, 'Histórico de lições geradas.');
    }

    /**
     * GET /api/v1/ai/lessons/{lesson}
     *
     * Retorna os detalhes de uma lição gerada específica.
     */
    public function show(int $id): JsonResponse
    {
        $user   = auth()->user();
        $lesson = $this->generator->getLesson($id, $user);

        if (!$lesson) {
            return $this->notFound('Lição não encontrada.');
        }

        return $this->success($lesson, 'Detalhes da lição.');
    }

    // ─── Internos ─────────────────────────────────────────────────────────────

    /**
     * Resolve os parâmetros da requisição.
     * Usa AdaptiveTutorService quando o usuário não fornecer os dados.
     *
     * @return array{0: string, 1: string, 2: array}
     */
    private function resolveParameters(
        $user,
        ?string $requestLevel,
        ?string $requestTopic,
        ?array  $requestGrammar,
    ): array {
        // Carrega dados adaptativos apenas se necessário
        $adaptive  = null;
        $needsAdap = !$requestLevel || !$requestTopic || $requestGrammar === null;

        if ($needsAdap) {
            $adaptivePlan = $this->adaptiveTutor->getAdaptivePlan($user);
            $adaptive     = $adaptivePlan;
        }

        // Nível
        $level = $requestLevel
            ?? $adaptive['level']['code']
            ?? 'A1';

        // Tópico: usar o primeiro tópico recomendado não praticado, ou fallback
        if ($requestTopic) {
            $topic = $requestTopic;
        } elseif (!empty($adaptive['recommended_topics']['recommended'])) {
            $topic = $adaptive['recommended_topics']['recommended'][0]['title'];
        } else {
            $topic = 'Daily Conversation';
        }

        // Foco gramatical: top erro do usuário, ou array vazio
        if ($requestGrammar !== null) {
            $grammarFocus = $requestGrammar;
        } elseif (!empty($adaptive['grammar_focus']['top_errors'])) {
            $grammarFocus = array_column(
                array_slice($adaptive['grammar_focus']['top_errors'], 0, 2),
                'explanation'
            );
        } else {
            $grammarFocus = [];
        }

        return [$level, $topic, $grammarFocus];
    }
}
