<?php

namespace App\Http\Controllers\API;

use App\Models\StudySession;
use App\Services\LearningEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class StudySessionController extends BaseController
{
    public function __construct(private LearningEngine $learningEngine)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        $query = StudySession::where('user_id', $user->id)
            ->with('lesson')
            ->orderByDesc('created_at');

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $sessions = $query->paginate($request->get('per_page', 15));

        return $this->paginated($sessions, 'Sessões de estudo listadas.');
    }

    public function dashboard(): JsonResponse
    {
        $user       = auth()->user();
        $statistics = $this->learningEngine->getUserStatistics($user);
        $dailyGoal  = $this->learningEngine->getDailyGoalProgress($user);

        $weeklyActivity = $this->getWeeklyActivity($user->id);
        $recommended    = $this->learningEngine->getRecommendedLessons($user, 3);

        return $this->success([
            'user' => [
                'name'        => $user->name,
                'level'       => $user->level,
                'total_xp'    => $user->total_xp,
                'streak_days' => $user->streak_days,
                'next_level_xp' => $this->learningEngine->getNextLevelXp($user->level),
            ],
            'statistics'      => $statistics,
            'daily_goal'      => $dailyGoal,
            'weekly_activity' => $weeklyActivity,
            'recommended_lessons' => $recommended->map(fn ($l) => [
                'id'       => $l->id,
                'title'    => $l->title,
                'level'    => $l->level,
                'category' => $l->category,
            ]),
        ], 'Dashboard carregado com sucesso.');
    }

    public function weeklyReport(): JsonResponse
    {
        $user        = auth()->user();
        $startOfWeek = Carbon::now()->startOfWeek();

        $sessions = StudySession::where('user_id', $user->id)
            ->where('created_at', '>=', $startOfWeek)
            ->get();

        $dailyData = [];
        for ($i = 0; $i < 7; $i++) {
            $day       = $startOfWeek->copy()->addDays($i);
            $daySessions = $sessions->filter(
                fn ($s) => Carbon::parse($s->created_at)->isSameDay($day)
            );

            $dailyData[] = [
                'date'             => $day->toDateString(),
                'day_name'         => $day->locale('pt_BR')->dayName,
                'sessions'         => $daySessions->count(),
                'minutes'          => $daySessions->sum('duration_minutes'),
                'xp_earned'        => $daySessions->sum('xp_earned'),
                'goal_reached'     => $daySessions->sum('duration_minutes') >= $user->daily_goal_minutes,
            ];
        }

        return $this->success([
            'week_start'       => $startOfWeek->toDateString(),
            'total_sessions'   => $sessions->count(),
            'total_minutes'    => $sessions->sum('duration_minutes'),
            'total_xp_earned'  => $sessions->sum('xp_earned'),
            'daily_breakdown'  => $dailyData,
            'goals_reached'    => collect($dailyData)->where('goal_reached', true)->count(),
        ], 'Relatório semanal gerado.');
    }

    private function getWeeklyActivity(int $userId): array
    {
        $startOfWeek = Carbon::now()->startOfWeek();

        return StudySession::where('user_id', $userId)
            ->where('created_at', '>=', $startOfWeek)
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('SUM(duration_minutes) as total_minutes')
            ->selectRaw('SUM(xp_earned) as total_xp')
            ->selectRaw('COUNT(*) as sessions')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }
}
