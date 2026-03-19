<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\PlacementQuestion;
use App\Models\User;
use App\Models\UserPlacementResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlacementTestService
{
    public function getQuestions(int $limit = 12): Collection
    {
        return PlacementQuestion::query()
            ->active()
            ->orderBy('display_order')
            ->limit(max(1, min($limit, 40)))
            ->get()
            ->map(fn (PlacementQuestion $q) => [
                'id' => $q->id,
                'question' => $q->question,
                'options' => $q->options ?? [],
                'skill' => $q->skill,
                'cefr_level' => $q->cefr_level,
                'weight' => $q->weight,
            ]);
    }

    public function evaluate(User $user, array $answers): array
    {
        $questionIds = collect($answers)->pluck('question_id')->filter()->unique()->values();

        $questions = PlacementQuestion::query()
            ->whereIn('id', $questionIds)
            ->active()
            ->get()
            ->keyBy('id');

        $answersByQuestion = collect($answers)->keyBy('question_id');

        $totalWeight = 0.0;
        $correctWeight = 0.0;
        $totalQuestions = 0;
        $correctAnswers = 0;

        $skills = [
            'grammar' => ['total' => 0, 'correct' => 0, 'score' => 0.0],
            'vocabulary' => ['total' => 0, 'correct' => 0, 'score' => 0.0],
            'reading' => ['total' => 0, 'correct' => 0, 'score' => 0.0],
        ];

        $answerSnapshot = [];

        foreach ($questions as $question) {
            $userAnswerRaw = (string) ($answersByQuestion->get($question->id)['answer'] ?? '');
            $userAnswer = $this->normalize($userAnswerRaw);
            $correctAnswer = $this->normalize($question->correct_answer);
            $isCorrect = $userAnswer !== '' && $userAnswer === $correctAnswer;
            $weight = max(0.1, (float) $question->weight);

            $totalQuestions++;
            $totalWeight += $weight;
            if ($isCorrect) {
                $correctAnswers++;
                $correctWeight += $weight;
            }

            $skillKey = $skills[$question->skill] ?? null;
            if ($skillKey !== null) {
                $skills[$question->skill]['total']++;
                if ($isCorrect) {
                    $skills[$question->skill]['correct']++;
                }
            }

            $answerSnapshot[] = [
                'question_id' => $question->id,
                'question' => $question->question,
                'skill' => $question->skill,
                'cefr_level' => $question->cefr_level,
                'user_answer' => $userAnswerRaw,
                'correct_answer' => $question->correct_answer,
                'is_correct' => $isCorrect,
                'weight' => $weight,
            ];
        }

        foreach ($skills as $skill => $row) {
            $skills[$skill]['score'] = $row['total'] > 0
                ? round(($row['correct'] / $row['total']) * 100, 2)
                : 0.0;
        }

        $scorePercentage = $totalWeight > 0 ? round(($correctWeight / $totalWeight) * 100, 2) : 0.0;
        $level = $this->mapScoreToLevel($scorePercentage);

        $recommendedLessons = Lesson::query()
            ->active()
            ->where('level', $level)
            ->whereHas('language', fn ($q) => $q->where('code', $user->target_language))
            ->orderBy('order')
            ->limit(5)
            ->get(['id', 'title', 'level', 'category', 'order'])
            ->map(fn ($lesson) => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'level' => $lesson->level,
                'category' => $lesson->category,
                'order' => $lesson->order,
            ])
            ->values()
            ->toArray();

        DB::transaction(function () use ($user, $totalQuestions, $correctAnswers, $scorePercentage, $level, $skills, $answerSnapshot) {
            UserPlacementResult::create([
                'user_id' => $user->id,
                'total_questions' => $totalQuestions,
                'correct_answers' => $correctAnswers,
                'score_percentage' => $scorePercentage,
                'level' => $level,
                'skill_breakdown' => $skills,
                'answers' => $answerSnapshot,
            ]);

            $user->update(['level' => $level]);
        });

        return [
            'level' => $level,
            'score_percentage' => $scorePercentage,
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctAnswers,
            'skill_breakdown' => $skills,
            'recommended_lessons' => $recommendedLessons,
        ];
    }

    public function latestResult(User $user): ?array
    {
        $result = $user->placementResults()->latest()->first();
        if (! $result) {
            return null;
        }

        return [
            'id' => $result->id,
            'level' => $result->level,
            'score_percentage' => $result->score_percentage,
            'total_questions' => $result->total_questions,
            'correct_answers' => $result->correct_answers,
            'skill_breakdown' => $result->skill_breakdown,
            'created_at' => $result->created_at->toISOString(),
        ];
    }

    private function mapScoreToLevel(float $score): string
    {
        return match (true) {
            $score < 25 => 'A1',
            $score < 45 => 'A2',
            $score < 65 => 'B1',
            $score < 80 => 'B2',
            $score < 92 => 'C1',
            default => 'C2',
        };
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $value)));
    }
}

