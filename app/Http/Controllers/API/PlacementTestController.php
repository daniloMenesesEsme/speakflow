<?php

namespace App\Http\Controllers\API;

use App\Services\PlacementTestService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PlacementTestController extends BaseController
{
    public function __construct(private readonly PlacementTestService $placement)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 12);
        $questions = $this->placement->getQuestions($limit);

        return $this->success([
            'questions' => $questions,
            'total' => $questions->count(),
        ], 'Questões de nivelamento carregadas com sucesso.');
    }

    public function submit(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'answers' => 'required|array|min:5',
            'answers.*.question_id' => 'required|integer|distinct',
            'answers.*.answer' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->error('Dados inválidos para avaliação.', $validator->errors(), 422);
        }

        /** @var User $user */
        $user = $request->user();
        $result = $this->placement->evaluate($user, $request->input('answers', []));

        return $this->success($result, 'Avaliação concluída com sucesso.');
    }

    public function latest(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $result = $this->placement->latestResult($user);
        if (! $result) {
            return $this->notFound('Nenhum resultado de nivelamento encontrado.');
        }

        return $this->success($result, 'Último resultado de nivelamento.');
    }

    public function initialPlan(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $plan = $this->placement->getInitialPlan($user);

        return $this->success($plan, 'Plano inicial carregado com sucesso.');
    }
}

