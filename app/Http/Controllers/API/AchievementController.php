<?php

namespace App\Http\Controllers\API;

use App\Models\Achievement;
use Illuminate\Http\JsonResponse;

class AchievementController extends BaseController
{
    public function index(): JsonResponse
    {
        $user         = auth()->user();
        $achievements = Achievement::active()->get();

        $earnedIds = $user->userAchievements()
            ->pluck('achievement_id')
            ->toArray();

        $formatted = $achievements->map(fn ($a) => [
            'id'              => $a->id,
            'title'           => $a->title,
            'description'     => $a->description,
            'xp_reward'       => $a->xp_reward,
            'icon'            => $a->icon,
            'category'        => $a->category,
            'earned'          => in_array($a->id, $earnedIds),
            'earned_at'       => in_array($a->id, $earnedIds)
                ? $user->userAchievements()
                    ->where('achievement_id', $a->id)
                    ->first()
                    ?->earned_at?->toISOString()
                : null,
        ]);

        return $this->success([
            'achievements' => $formatted,
            'stats'        => [
                'total'  => $achievements->count(),
                'earned' => count($earnedIds),
            ],
        ]);
    }

    public function myAchievements(): JsonResponse
    {
        $user = auth()->user();

        $userAchievements = $user->userAchievements()
            ->with('achievement')
            ->orderByDesc('earned_at')
            ->get()
            ->map(fn ($ua) => [
                'id'          => $ua->achievement->id,
                'title'       => $ua->achievement->title,
                'description' => $ua->achievement->description,
                'xp_reward'   => $ua->achievement->xp_reward,
                'icon'        => $ua->achievement->icon,
                'category'    => $ua->achievement->category,
                'earned_at'   => $ua->earned_at->toISOString(),
            ]);

        return $this->success([
            'achievements' => $userAchievements,
            'total'        => $userAchievements->count(),
            'total_xp'     => $userAchievements->sum('xp_reward'),
        ]);
    }
}
