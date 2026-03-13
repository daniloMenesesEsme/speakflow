<?php

namespace App\Http\Controllers\API;

use App\Models\Exercise;
use App\Models\Lesson;
use App\Models\StudySession;
use App\Models\UserExerciseAttempt;
use App\Services\AchievementService;
use App\Services\DailyActivityService;
use App\Services\DailyMissionService;
use App\Services\LeaderboardService;
use App\Services\LearningEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ExerciseController extends BaseController
{
    public function __construct(
        private LearningEngine       $learningEngine,
        private AchievementService   $achievementService,
        private DailyActivityService $dailyActivity,
        private LeaderboardService   $leaderboard,
        private DailyMissionService  $missions,
    ) {
    }

    // ─── GET /exercises ───────────────────────────────────────────────────────
    // Lista exercícios do idioma-alvo do usuário.
    // Filtros opcionais: lesson_id, type, difficulty, level
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        $query = Exercise::with('lesson.language')
            ->whereHas('lesson.language', fn ($q) => $q->where('code', $user->target_language))
            ->whereHas('lesson', fn ($q) => $q->active());

        if ($request->filled('lesson_id')) {
            $query->where('lesson_id', $request->integer('lesson_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }

        if ($request->filled('level')) {
            $query->whereHas('lesson', fn ($q) => $q->where('level', $request->level));
        }

        $exercises = $query->orderBy('lesson_id')->orderBy('order')->paginate($request->integer('per_page', 20));

        return $this->paginated(
            $exercises->through(fn ($e) => $this->formatExercise($e)),
            'Exercícios listados com sucesso.'
        );
    }

    // ─── GET /exercises/{exercise} ────────────────────────────────────────────
    public function show(Exercise $exercise): JsonResponse
    {
        $exercise->load('lesson.language');

        return $this->success(
            array_merge($this->formatExercise($exercise), [
                'lesson' => [
                    'id'       => $exercise->lesson->id,
                    'title'    => $exercise->lesson->title,
                    'level'    => $exercise->lesson->level,
                    'category' => $exercise->lesson->category,
                    'language' => $exercise->lesson->language
                        ? ['id' => $exercise->lesson->language->id, 'code' => $exercise->lesson->language->code]
                        : null,
                ],
            ])
        );
    }

    public function start(Lesson $lesson): JsonResponse
    {
        $exercises = $lesson->exercises()
            ->orderBy('order')
            ->get()
            ->map(fn ($e) => [
                'id'         => $e->id,
                'type'       => $e->type,
                'question'   => $e->question,
                'options'    => $e->options,
                'difficulty' => $e->difficulty,
                'points'     => $e->points,
            ]);

        if ($exercises->isEmpty()) {
            return $this->error('Esta lição não possui exercícios.', null, 404);
        }

        $session = StudySession::create([
            'user_id'   => auth()->id(),
            'lesson_id' => $lesson->id,
            'completed' => false,
        ]);

        return $this->success([
            'session_id' => $session->id,
            'lesson'     => [
                'id'    => $lesson->id,
                'title' => $lesson->title,
                'level' => $lesson->level,
            ],
            'exercises'  => $exercises,
            'total'      => $exercises->count(),
        ], 'Sessão de exercícios iniciada.');
    }

    /**
     * POST /exercises/{exercise}/answer
     *
     * Processa a resposta do usuário, registra a tentativa e concede XP
     * apenas na primeira vez que o exercício for respondido corretamente
     * (evita farm de pontos em tentativas repetidas).
     */
    public function submitAnswer(Request $request, Exercise $exercise): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'answer' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->error('Dados inválidos.', $validator->errors(), 422);
        }

        $user      = auth()->user();
        $isCorrect = $exercise->checkAnswer($request->answer);

        // ── Verificar histórico de tentativas ────────────────────────────────
        $previousAttempts = UserExerciseAttempt::where('user_id', $user->id)
            ->where('exercise_id', $exercise->id)
            ->orderByDesc('created_at')
            ->get();

        $attemptNumber     = $previousAttempts->count() + 1;
        $alreadyCorrect    = $previousAttempts->contains('correct', true);

        // XP só é concedido na primeira resposta correta (anti-farm)
        $pointsEarned = ($isCorrect && ! $alreadyCorrect) ? $exercise->points : 0;

        // ── Transação: registrar tentativa + atualizar XP ────────────────────
        DB::transaction(function () use ($user, $exercise, $request, $isCorrect, $pointsEarned, $attemptNumber) {
            UserExerciseAttempt::create([
                'user_id'        => $user->id,
                'exercise_id'    => $exercise->id,
                'answer'         => $request->answer,
                'correct'        => $isCorrect,
                'points_earned'  => $pointsEarned,
                'attempt_number' => $attemptNumber,
            ]);

            if ($pointsEarned > 0) {
                $user->increment('total_xp', $pointsEarned);
            }
        });

        // ── Registrar atividade diária, ranking semanal e conquistas ─────────
        if ($isCorrect) {
            $this->dailyActivity->record(
                user:            $user,
                xpDelta:         $pointsEarned,
                exerciseCorrect: true,
            );

            $this->leaderboard->record(
                user:            $user,
                xpDelta:         $pointsEarned,
                exerciseCorrect: true,
            );

            $this->achievementService->checkAndAwardAchievements($user->fresh());
            $this->missions->updateProgress($user, 'exercise');
        }

        // ── Montar resposta ───────────────────────────────────────────────────
        $message = match (true) {
            $isCorrect && $pointsEarned > 0 => 'Resposta correta! Você ganhou ' . $pointsEarned . ' XP.',
            $isCorrect && $alreadyCorrect   => 'Resposta correta! (XP já concedido anteriormente)',
            default                         => 'Resposta incorreta. Tente novamente!',
        };

        return $this->success([
            'correct'         => $isCorrect,
            'correct_answer'  => $exercise->correct_answer,
            'explanation'     => $exercise->explanation,
            'points_earned'   => $pointsEarned,
            'attempt_number'  => $attemptNumber,
            'total_xp'        => $user->fresh()->total_xp,
            'xp_info' => $alreadyCorrect && $isCorrect
                ? 'XP não concedido: este exercício já foi respondido corretamente antes.'
                : null,
        ], $message);
    }

    /** @deprecated Use submitAnswer. Mantido para compatibilidade com sessões de estudo. */
    public function answer(Request $request, Exercise $exercise): JsonResponse
    {
        return $this->submitAnswer($request, $exercise);
    }

    public function complete(Request $request, StudySession $session): JsonResponse
    {
        if ($session->user_id !== auth()->id()) {
            return $this->unauthorized('Esta sessão não pertence a você.');
        }

        if ($session->completed) {
            return $this->error('Esta sessão já foi concluída.', null, 422);
        }

        $validator = Validator::make($request->all(), [
            'correct_answers'     => 'required|integer|min:0',
            'total_answers'       => 'required|integer|min:1',
            'duration_minutes'    => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->error('Dados inválidos.', $validator->errors(), 422);
        }

        $user           = auth()->user();
        $correctAnswers = $request->correct_answers;
        $totalAnswers   = $request->total_answers;
        $accuracy       = $totalAnswers > 0 ? round(($correctAnswers / $totalAnswers) * 100, 2) : 0;

        $lessonCompleted = $accuracy >= 80 && $session->lesson_id !== null;

        $xpEarned = $this->learningEngine->calculateXpForSession(
            $correctAnswers,
            $totalAnswers,
            $lessonCompleted,
            $user->streak_days
        );

        $session->update([
            'correct_answers'     => $correctAnswers,
            'exercises_completed' => $totalAnswers,
            'accuracy_percentage' => $accuracy,
            'duration_minutes'    => $request->duration_minutes,
            'xp_earned'           => $xpEarned,
            'completed'           => true,
        ]);

        $progressResult = $this->learningEngine->updateUserProgress($user, $session);

        if ($session->lesson_id) {
            $this->learningEngine->updateLessonProgress(
                $user,
                $session->lesson,
                $correctAnswers,
                $totalAnswers
            );
        }

        $newAchievements = $this->achievementService->checkAndAwardAchievements(
            $user->fresh()
        );

        return $this->success([
            'session'          => [
                'id'                  => $session->id,
                'correct_answers'     => $correctAnswers,
                'total_answers'       => $totalAnswers,
                'accuracy_percentage' => $accuracy,
                'xp_earned'           => $xpEarned,
                'duration_minutes'    => $request->duration_minutes,
            ],
            'progress'         => $progressResult,
            'new_achievements' => collect($newAchievements)->map(fn ($a) => [
                'id'          => $a->id,
                'title'       => $a->title,
                'description' => $a->description,
                'xp_reward'   => $a->xp_reward,
                'icon'        => $a->icon,
            ]),
            'daily_goal'       => $this->learningEngine->getDailyGoalProgress($user->fresh()),
        ], 'Sessão concluída com sucesso!');
    }

    // ─── Formato padrão de exercício (sem correct_answer) ────────────────────
    private function formatExercise(Exercise $exercise): array
    {
        return [
            'id'         => $exercise->id,
            'lesson_id'  => $exercise->lesson_id,
            'type'       => $exercise->type,
            'question'   => $exercise->question,
            'options'    => $exercise->options,
            'difficulty' => $exercise->difficulty,
            'order'      => $exercise->order,
            'points'     => $exercise->points,
        ];
    }
}
