<?php

namespace App\Services;

use App\Models\AiUsageLog;
use App\Models\GeneratedLesson;
use App\Models\UsageLog;
use Illuminate\Support\Facades\DB;

/**
 * Analisa custos de IA usando apenas tabelas existentes:
 *   - ai_usage_logs  → custo real de tokens (chat + lesson generation)
 *   - generated_lessons → metadados das lições geradas (driver, level, topic)
 *   - usage_logs     → contagem de eventos por feature/plano
 *
 * Não cria novas tabelas. Usa ai_usage_logs como fonte primária de custo.
 * Convenção:  conversation_id IS NOT NULL → feature "chat"
 *             conversation_id IS NULL     → feature "lesson_generation"
 */
class AiCostAnalyticsService
{
    // ─── 1. Custo hoje ──────────────────────────────────────────────────────

    public function getTodayCost(): array
    {
        $rows = AiUsageLog::whereDate('created_at', today())
            ->select(
                DB::raw('SUM(prompt_tokens)     AS prompt_tokens'),
                DB::raw('SUM(completion_tokens) AS completion_tokens'),
                DB::raw('SUM(total_tokens)      AS total_tokens'),
                DB::raw('SUM(estimated_cost)    AS total_cost'),
                DB::raw('COUNT(*)               AS total_requests'),
                DB::raw("SUM(CASE WHEN conversation_id IS NOT NULL THEN estimated_cost ELSE 0 END) AS chat_cost"),
                DB::raw("SUM(CASE WHEN conversation_id IS NULL     THEN estimated_cost ELSE 0 END) AS lesson_cost"),
                DB::raw("SUM(CASE WHEN conversation_id IS NOT NULL THEN 1 ELSE 0 END) AS chat_requests"),
                DB::raw("SUM(CASE WHEN conversation_id IS NULL     THEN 1 ELSE 0 END) AS lesson_requests"),
            )
            ->first();

        $lessonsToday = GeneratedLesson::where('driver', GeneratedLesson::DRIVER_OPENAI)
            ->whereDate('created_at', today())
            ->count();

        return [
            'date'             => today()->toDateString(),
            'total_requests'   => (int) ($rows->total_requests ?? 0),
            'total_tokens'     => (int) ($rows->total_tokens ?? 0),
            'prompt_tokens'    => (int) ($rows->prompt_tokens ?? 0),
            'completion_tokens'=> (int) ($rows->completion_tokens ?? 0),
            'total_cost_usd'   => round((float) ($rows->total_cost ?? 0), 6),
            'by_feature'       => [
                'chat' => [
                    'requests' => (int) ($rows->chat_requests ?? 0),
                    'cost_usd' => round((float) ($rows->chat_cost ?? 0), 6),
                ],
                'lesson_generation' => [
                    'requests' => (int) ($rows->lesson_requests ?? 0),
                    'lessons_generated' => $lessonsToday,
                    'cost_usd' => round((float) ($rows->lesson_cost ?? 0), 6),
                ],
            ],
        ];
    }

    // ─── 2. Custo por usuário ────────────────────────────────────────────────

    public function getCostByUser(int $userId): array
    {
        $logs = AiUsageLog::where('user_id', $userId)
            ->select(
                DB::raw('SUM(prompt_tokens)     AS prompt_tokens'),
                DB::raw('SUM(completion_tokens) AS completion_tokens'),
                DB::raw('SUM(total_tokens)      AS total_tokens'),
                DB::raw('SUM(estimated_cost)    AS total_cost'),
                DB::raw('COUNT(*)               AS total_requests'),
                DB::raw("SUM(CASE WHEN conversation_id IS NOT NULL THEN estimated_cost ELSE 0 END) AS chat_cost"),
                DB::raw("SUM(CASE WHEN conversation_id IS NULL     THEN estimated_cost ELSE 0 END) AS lesson_cost"),
                DB::raw("SUM(CASE WHEN conversation_id IS NOT NULL THEN 1 ELSE 0 END) AS chat_requests"),
                DB::raw("SUM(CASE WHEN conversation_id IS NULL     THEN 1 ELSE 0 END) AS lesson_requests"),
            )
            ->first();

        $lessonsGenerated = GeneratedLesson::where('user_id', $userId)
            ->where('driver', GeneratedLesson::DRIVER_OPENAI)
            ->count();

        $modelBreakdown = AiUsageLog::where('user_id', $userId)
            ->select('model', DB::raw('COUNT(*) AS requests'), DB::raw('SUM(total_tokens) AS tokens'), DB::raw('SUM(estimated_cost) AS cost'))
            ->groupBy('model')
            ->get()
            ->map(fn ($r) => [
                'model'    => $r->model,
                'requests' => (int) $r->requests,
                'tokens'   => (int) $r->tokens,
                'cost_usd' => round((float) $r->cost, 6),
            ]);

        $usageEvents = UsageLog::where('user_id', $userId)
            ->select('type', DB::raw('SUM(quantity) AS total_quantity'))
            ->groupBy('type')
            ->pluck('total_quantity', 'type');

        return [
            'user_id'          => $userId,
            'total_requests'   => (int) ($logs->total_requests ?? 0),
            'total_tokens'     => (int) ($logs->total_tokens ?? 0),
            'prompt_tokens'    => (int) ($logs->prompt_tokens ?? 0),
            'completion_tokens'=> (int) ($logs->completion_tokens ?? 0),
            'total_cost_usd'   => round((float) ($logs->total_cost ?? 0), 6),
            'by_feature'       => [
                'chat' => [
                    'requests' => (int) ($logs->chat_requests ?? 0),
                    'cost_usd' => round((float) ($logs->chat_cost ?? 0), 6),
                ],
                'lesson_generation' => [
                    'requests'         => (int) ($logs->lesson_requests ?? 0),
                    'lessons_generated'=> $lessonsGenerated,
                    'cost_usd'         => round((float) ($logs->lesson_cost ?? 0), 6),
                ],
            ],
            'by_model'         => $modelBreakdown->values(),
            'usage_events'     => $usageEvents,
        ];
    }

    // ─── 3. Tendência diária de custos ──────────────────────────────────────

    public function getDailyCostTrend(int $days = 30): array
    {
        $from = now()->subDays($days - 1)->startOfDay();

        $aiRows = AiUsageLog::where('created_at', '>=', $from)
            ->select(
                DB::raw('DATE(created_at) AS day'),
                DB::raw('SUM(total_tokens)   AS total_tokens'),
                DB::raw('SUM(estimated_cost) AS total_cost'),
                DB::raw('COUNT(*)            AS total_requests'),
                DB::raw("SUM(CASE WHEN conversation_id IS NOT NULL THEN estimated_cost ELSE 0 END) AS chat_cost"),
                DB::raw("SUM(CASE WHEN conversation_id IS NULL     THEN estimated_cost ELSE 0 END) AS lesson_cost"),
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy(DB::raw('DATE(created_at)'))
            ->get()
            ->keyBy('day');

        // Preenche todos os dias do período, mesmo sem registros
        $trend = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $row  = $aiRows->get($date);
            $trend[] = [
                'date'           => $date,
                'total_requests' => (int) ($row->total_requests ?? 0),
                'total_tokens'   => (int) ($row->total_tokens   ?? 0),
                'total_cost_usd' => round((float) ($row->total_cost  ?? 0), 6),
                'chat_cost_usd'  => round((float) ($row->chat_cost   ?? 0), 6),
                'lesson_cost_usd'=> round((float) ($row->lesson_cost ?? 0), 6),
            ];
        }

        $totals = [
            'total_requests' => array_sum(array_column($trend, 'total_requests')),
            'total_tokens'   => array_sum(array_column($trend, 'total_tokens')),
            'total_cost_usd' => round(array_sum(array_column($trend, 'total_cost_usd')), 6),
            'avg_daily_cost' => round(array_sum(array_column($trend, 'total_cost_usd')) / max($days, 1), 6),
        ];

        return [
            'period_days' => $days,
            'from'        => now()->subDays($days - 1)->toDateString(),
            'to'          => today()->toDateString(),
            'totals'      => $totals,
            'trend'       => $trend,
        ];
    }

    // ─── 4. Custo por feature ────────────────────────────────────────────────

    public function getCostByFeature(): array
    {
        // Custo de chat (ai_usage_logs com conversation_id)
        $chatStats = AiUsageLog::whereNotNull('conversation_id')
            ->select(
                DB::raw('COUNT(*)            AS requests'),
                DB::raw('SUM(total_tokens)   AS tokens'),
                DB::raw('SUM(estimated_cost) AS cost'),
                DB::raw('COUNT(DISTINCT user_id) AS unique_users'),
            )
            ->first();

        // Custo de lesson generation (ai_usage_logs sem conversation_id)
        $lessonStats = AiUsageLog::whereNull('conversation_id')
            ->select(
                DB::raw('COUNT(*)            AS requests'),
                DB::raw('SUM(total_tokens)   AS tokens'),
                DB::raw('SUM(estimated_cost) AS cost'),
                DB::raw('COUNT(DISTINCT user_id) AS unique_users'),
            )
            ->first();

        // Dados extras de geração de lições (nível e tópico mais gerados)
        $topLevels = GeneratedLesson::where('driver', GeneratedLesson::DRIVER_OPENAI)
            ->select('level', DB::raw('COUNT(*) AS count'))
            ->groupBy('level')
            ->orderByDesc('count')
            ->limit(5)
            ->pluck('count', 'level');

        $topTopics = GeneratedLesson::where('driver', GeneratedLesson::DRIVER_OPENAI)
            ->select('topic', DB::raw('COUNT(*) AS count'))
            ->groupBy('topic')
            ->orderByDesc('count')
            ->limit(5)
            ->pluck('count', 'topic');

        // Contagem de eventos no usage_logs para contexto adicional
        $usageCounts = UsageLog::select('type', DB::raw('COUNT(*) AS events'), DB::raw('SUM(quantity) AS quantity'))
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        // Custo total por modelo de IA
        $byModel = AiUsageLog::select(
                'model',
                DB::raw('COUNT(*)            AS requests'),
                DB::raw('SUM(total_tokens)   AS tokens'),
                DB::raw('SUM(estimated_cost) AS cost'),
            )
            ->groupBy('model')
            ->orderByDesc('cost')
            ->get()
            ->map(fn ($r) => [
                'model'    => $r->model,
                'requests' => (int) $r->requests,
                'tokens'   => (int) $r->tokens,
                'cost_usd' => round((float) $r->cost, 6),
            ]);

        $totalCost = ((float) ($chatStats->cost ?? 0)) + ((float) ($lessonStats->cost ?? 0));

        return [
            'total_cost_usd' => round($totalCost, 6),
            'features'       => [
                'chat' => [
                    'requests'     => (int) ($chatStats->requests    ?? 0),
                    'tokens'       => (int) ($chatStats->tokens      ?? 0),
                    'cost_usd'     => round((float) ($chatStats->cost ?? 0), 6),
                    'unique_users' => (int) ($chatStats->unique_users ?? 0),
                    'pct_of_total' => $totalCost > 0
                        ? round(((float) ($chatStats->cost ?? 0)) / $totalCost * 100, 2)
                        : 0,
                    'usage_events' => (int) ($usageCounts->get('ai_message')?->quantity ?? 0),
                ],
                'lesson_generation' => [
                    'requests'       => (int) ($lessonStats->requests    ?? 0),
                    'tokens'         => (int) ($lessonStats->tokens      ?? 0),
                    'cost_usd'       => round((float) ($lessonStats->cost ?? 0), 6),
                    'unique_users'   => (int) ($lessonStats->unique_users ?? 0),
                    'pct_of_total'   => $totalCost > 0
                        ? round(((float) ($lessonStats->cost ?? 0)) / $totalCost * 100, 2)
                        : 0,
                    'usage_events'   => (int) ($usageCounts->get('lesson_generation')?->quantity ?? 0),
                    'top_levels'     => $topLevels,
                    'top_topics'     => $topTopics,
                ],
            ],
            'by_model' => $byModel->values(),
        ];
    }
}
