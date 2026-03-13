<?php

namespace App\Http\Controllers\API;

use App\Models\Lesson;
use App\Models\Language;
use App\Models\UserLessonProgress;
use App\Services\AchievementService;
use App\Services\DailyActivityService;
use App\Services\LeaderboardService;
use App\Services\LearningEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LessonController extends BaseController
{
    public function __construct(
        private LearningEngine       $learningEngine,
        private DailyActivityService $dailyActivity,
        private AchievementService   $achievementService,
        private LeaderboardService   $leaderboard,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        $query = Lesson::with('language')
            ->active()
            ->ordered();

        if ($request->filled('language_code')) {
            $query->whereHas('language', fn ($q) => $q->where('code', $request->language_code));
        } else {
            $query->whereHas('language', fn ($q) => $q->where('code', $user->target_language));
        }

        if ($request->filled('level')) {
            $query->byLevel($request->level);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $lessons = $query->paginate($request->get('per_page', 15));

        $progressMap = $user->lessonProgress()
            ->whereIn('lesson_id', collect($lessons->items())->pluck('id'))
            ->get()
            ->keyBy('lesson_id');

        $items = collect($lessons->items())->map(function ($lesson) use ($progressMap) {
            $progress = $progressMap->get($lesson->id);
            return array_merge($this->formatLesson($lesson), [
                'progress' => $progress ? [
                    'completion_percentage' => $progress->completion_percentage,
                    'best_score'            => $progress->best_score,
                    'times_completed'       => $progress->times_completed,
                    'last_accessed_at'      => $progress->last_accessed_at?->toISOString(),
                ] : null,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Lições listadas com sucesso.',
            'data'    => $items,
            'meta'    => [
                'current_page' => $lessons->currentPage(),
                'per_page'     => $lessons->perPage(),
                'total'        => $lessons->total(),
                'last_page'    => $lessons->lastPage(),
            ],
        ]);
    }

    public function show(Lesson $lesson): JsonResponse
    {
        $lesson->load(['language', 'phrases', 'exercises' => fn ($q) => $q->orderBy('order')]);
        $user = auth()->user();

        $progress = $user->lessonProgress()
            ->where('lesson_id', $lesson->id)
            ->first();

        return $this->success([
            'lesson'   => $this->formatLesson($lesson),
            'phrases'  => $lesson->phrases->map(fn ($p) => [
                'id'              => $p->id,
                'english_text'    => $p->english_text,
                'portuguese_text' => $p->portuguese_text,
                'audio_path'      => $p->audio_path,
                'difficulty'      => $p->difficulty,
                'phonetic'        => $p->phonetic,
            ]),
            'exercises' => $lesson->exercises->map(fn ($e) => [
                'id'         => $e->id,
                'type'       => $e->type,
                'question'   => $e->question,
                'options'    => $e->options,
                'difficulty' => $e->difficulty,
                'order'      => $e->order,
                'points'     => $e->points,
            ]),
            'progress' => $progress ? [
                'completion_percentage' => $progress->completion_percentage,
                'best_score'            => $progress->best_score,
                'times_completed'       => $progress->times_completed,
            ] : null,
        ]);
    }

    public function recommended(): JsonResponse
    {
        $user    = auth()->user();
        $lessons = $this->learningEngine->getRecommendedLessons($user);

        return $this->success(
            $lessons->map(fn ($l) => $this->formatLesson($l)),
            'Lições recomendadas para você.'
        );
    }

    public function categories(Request $request): JsonResponse
    {
        $languageCode = $request->get('language_code', auth()->user()->target_language);

        $categories = Lesson::whereHas('language', fn ($q) => $q->where('code', $languageCode))
            ->active()
            ->distinct()
            ->pluck('category')
            ->values();

        return $this->success($categories);
    }

    /**
     * POST /lessons/{lesson}/complete
     *
     * Marca a lição como concluída e calcula o XP total ganho pelo
     * usuário nos exercícios desta lição. Idempotente: chamar duas vezes
     * retorna o progresso existente sem alterar XP novamente.
     */
    public function completeLesson(Request $request, Lesson $lesson): JsonResponse
    {
        $user = auth()->user();

        // ── Verificar se já foi concluída ────────────────────────────────────
        $existing = UserLessonProgress::where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->first();

        if ($existing?->isCompleted()) {
            return $this->success([
                'lesson_id'  => $lesson->id,
                'completed'  => true,
                'xp_earned'  => $existing->xp_earned,
                'total_xp'   => $user->total_xp,
                'completed_at' => $existing->completed_at?->toISOString(),
            ], 'Esta lição já foi concluída anteriormente.');
        }

        // ── Calcular XP ganho nos exercícios desta lição ─────────────────────
        // Soma apenas os pontos das tentativas CORRETAS e ÚNICAS por exercício
        // (a primeira acerto de cada exercício, para evitar dupla contagem).
        $xpFromExercises = \App\Models\UserExerciseAttempt::where('user_id', $user->id)
            ->where('correct', true)
            ->where('points_earned', '>', 0)
            ->whereIn('exercise_id', $lesson->exercises()->pluck('id'))
            ->sum('points_earned');

        // XP base da lição somado ao XP dos exercícios completados
        $xpEarned = max((int) $xpFromExercises, $lesson->xp_reward ?? 0);

        // ── Transação: gravar progresso + incrementar XP do usuário ──────────
        DB::transaction(function () use ($user, $lesson, $existing, $xpEarned) {
            $now = now();

            if ($existing) {
                $existing->update([
                    'completed'             => true,
                    'xp_earned'             => $xpEarned,
                    'completed_at'          => $now,
                    'completion_percentage' => 100,
                    'times_completed'       => $existing->times_completed + 1,
                    'last_accessed_at'      => $now,
                ]);
            } else {
                UserLessonProgress::create([
                    'user_id'               => $user->id,
                    'lesson_id'             => $lesson->id,
                    'completed'             => true,
                    'xp_earned'             => $xpEarned,
                    'completed_at'          => $now,
                    'completion_percentage' => 100,
                    'times_completed'       => 1,
                    'best_score'            => 100,
                    'last_accessed_at'      => $now,
                ]);
            }

            // Atualiza XP somente na primeira conclusão
            if ($xpEarned > 0 && ! $existing?->isCompleted()) {
                $user->increment('total_xp', $xpEarned);
            }
        });

        // ── Registrar atividade diária + ranking semanal ──────────────────────
        $this->dailyActivity->record(
            user:             $user,
            xpDelta:          $xpEarned,
            lessonCompleted:  true,
        );

        $this->leaderboard->record(
            user:             $user,
            xpDelta:          $xpEarned,
            lessonCompleted:  true,
        );

        // ── Verificar e conceder conquistas desbloqueadas ─────────────────────
        $newAchievements = $this->achievementService->checkAndAwardAchievements($user->fresh());

        $freshUser = $user->fresh();

        return $this->success([
            'lesson_id'        => $lesson->id,
            'lesson_title'     => $lesson->title,
            'completed'        => true,
            'xp_earned'        => $xpEarned,
            'total_xp'         => $freshUser->total_xp,
            'completed_at'     => now()->toISOString(),
            'new_achievements' => collect($newAchievements)->map(fn ($a) => [
                'id'          => $a->id,
                'title'       => $a->title,
                'description' => $a->description,
                'xp_reward'   => $a->xp_reward,
                'icon'        => $a->icon,
            ])->values(),
        ], 'Lição concluída com sucesso!');
    }

    private function formatLesson(Lesson $lesson): array
    {
        return [
            'id'          => $lesson->id,
            'title'       => $lesson->title,
            'level'       => $lesson->level,
            'category'    => $lesson->category,
            'order'       => $lesson->order,
            'description' => $lesson->description,
            'thumbnail'   => $lesson->thumbnail,
            'xp_reward'   => $lesson->xp_reward,
            'language'    => $lesson->language ? [
                'id'   => $lesson->language->id,
                'name' => $lesson->language->name,
                'code' => $lesson->language->code,
            ] : null,
        ];
    }
}
