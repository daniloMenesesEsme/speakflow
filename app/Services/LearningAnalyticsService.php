<?php

namespace App\Services;

use App\Models\AiConversation;
use App\Models\AiCorrection;
use App\Models\AiMessage;
use App\Models\AiUsageLog;
use App\Models\AiVoiceMessage;
use App\Models\ConversationTopic;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserDailyActivity;
use App\Models\UserExerciseAttempt;
use App\Models\UserLessonProgress;
use App\Models\UserWeeklyXp;
use Illuminate\Support\Facades\DB;

class LearningAnalyticsService
{
    // ─── API pública ─────────────────────────────────────────────────────────

    /**
     * Consolida todas as estatísticas em um único payload para o endpoint /analytics.
     */
    public function getFullReport(User $user): array
    {
        return [
            'general'   => $this->getGeneralStats($user),
            'learning'  => $this->getLearningStats($user),
            'topics'    => $this->getTopicStats($user),
            'ai_usage'  => $this->getAiUsageStats($user),
        ];
    }

    // ─── Estatísticas gerais ──────────────────────────────────────────────────

    /**
     * Visão geral do progresso do usuário coletando dados das principais tabelas.
     */
    public function getGeneralStats(User $user): array
    {
        $userId = $user->id;

        // Lições concluídas
        $lessonsCompleted = UserLessonProgress::where('user_id', $userId)
            ->where('completed', true)
            ->count();

        // Exercícios respondidos (ao menos uma tentativa correta)
        $exercisesCompleted = UserExerciseAttempt::where('user_id', $userId)
            ->where('correct', true)
            ->distinct('exercise_id')
            ->count('exercise_id');

        // Exercícios tentados no total
        $exercisesAttempted = UserExerciseAttempt::where('user_id', $userId)->count();

        // Taxa de acerto
        $correctAttempts = UserExerciseAttempt::where('user_id', $userId)
            ->where('correct', true)
            ->count();
        $accuracy = $exercisesAttempted > 0
            ? round(($correctAttempts / $exercisesAttempted) * 100, 1)
            : 0;

        // Conversas com IA
        $conversationsCount = AiConversation::where('user_id', $userId)->count();

        // Mensagens de voz
        $voiceMessagesCount = AiVoiceMessage::where('user_id', $userId)->count();

        // Conquistas desbloqueadas
        $achievementsEarned = UserAchievement::where('user_id', $userId)->count();

        // XP total e semanal
        $totalXp   = $user->total_xp ?? 0;
        $weeklyXp  = UserWeeklyXp::where('user_id', $userId)
            ->where('week_start', now()->startOfWeek()->toDateString())
            ->value('xp') ?? 0;

        // Dias de atividade totais
        $activeDays = UserDailyActivity::where('user_id', $userId)->count();

        // Dias estudados este mês
        $activeDaysThisMonth = UserDailyActivity::where('user_id', $userId)
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->count();

        // Tempo total estudado (minutos)
        $totalMinutes = UserDailyActivity::where('user_id', $userId)
            ->sum('xp_earned');  // proxy: cada XP ≈ atividade registrada

        return [
            'total_xp'               => $totalXp,
            'weekly_xp'              => $weeklyXp,
            'streak_days'            => $user->streak_days ?? 0,
            'level'                  => $user->level ?? 'A1',
            'lessons_completed'      => $lessonsCompleted,
            'exercises_completed'    => $exercisesCompleted,
            'exercises_attempted'    => $exercisesAttempted,
            'accuracy_rate'          => $accuracy,
            'conversations_count'    => $conversationsCount,
            'voice_messages_count'   => $voiceMessagesCount,
            'achievements_earned'    => $achievementsEarned,
            'active_days_total'      => $activeDays,
            'active_days_this_month' => $activeDaysThisMonth,
        ];
    }

    // ─── Estatísticas de aprendizado ──────────────────────────────────────────

    /**
     * Análise dos erros gramaticais do usuário com base em ai_corrections.
     * Identifica padrões de erro mais frequentes para orientar o estudo.
     */
    public function getLearningStats(User $user): array
    {
        $userId = $user->id;

        $totalCorrections = AiCorrection::where('user_id', $userId)->count();

        // Top 5 erros mais frequentes (agrupa por explicação similar)
        $topErrors = AiCorrection::where('user_id', $userId)
            ->select('explanation', DB::raw('COUNT(*) as occurrences'))
            ->groupBy('explanation')
            ->orderByDesc('occurrences')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'explanation' => $row->explanation,
                'occurrences' => (int) $row->occurrences,
            ])
            ->toArray();

        // Proporção de correções por idioma
        $byLanguage = AiCorrection::where('user_id', $userId)
            ->select('language', DB::raw('COUNT(*) as total'))
            ->groupBy('language')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['language' => $r->language, 'corrections' => (int) $r->total])
            ->toArray();

        // Evolução: correções na última semana vs. semana anterior
        $correctionsThisWeek = AiCorrection::where('user_id', $userId)
            ->where('created_at', '>=', now()->startOfWeek())
            ->count();

        $correctionsPrevWeek = AiCorrection::where('user_id', $userId)
            ->whereBetween('created_at', [
                now()->subWeek()->startOfWeek(),
                now()->subWeek()->endOfWeek(),
            ])
            ->count();

        // Tendência: se diminuiu = está melhorando
        $trend = match (true) {
            $correctionsPrevWeek === 0                         => 'no_data',
            $correctionsThisWeek < $correctionsPrevWeek        => 'improving',
            $correctionsThisWeek === $correctionsPrevWeek      => 'stable',
            default                                            => 'needs_attention',
        };

        return [
            'total_corrections'       => $totalCorrections,
            'corrections_this_week'   => $correctionsThisWeek,
            'corrections_prev_week'   => $correctionsPrevWeek,
            'grammar_trend'           => $trend,
            'most_common_errors'      => $topErrors,
            'corrections_by_language' => $byLanguage,
        ];
    }

    // ─── Estatísticas por tópico ──────────────────────────────────────────────

    /**
     * Quais tópicos o usuário mais pratica, com contagem de conversas e mensagens.
     */
    public function getTopicStats(User $user): array
    {
        $userId = $user->id;

        // Conversas com tópico estruturado (topic_id)
        $topicsWithId = AiConversation::where('user_id', $userId)
            ->whereNotNull('topic_id')
            ->select('topic_id', DB::raw('COUNT(*) as conversations'), DB::raw('SUM(messages_count) as messages'))
            ->groupBy('topic_id')
            ->orderByDesc('conversations')
            ->with('conversationTopic:id,title,level,icon')
            ->get()
            ->map(fn ($c) => [
                'topic_id'        => $c->topic_id,
                'title'           => $c->conversationTopic?->title ?? 'Unknown',
                'level'           => $c->conversationTopic?->level ?? '-',
                'icon'            => $c->conversationTopic?->icon ?? '💬',
                'conversations'   => (int) $c->conversations,
                'total_messages'  => (int) $c->messages,
            ]);

        // Conversas com tópico livre (apenas texto, sem topic_id)
        $freeTopics = AiConversation::where('user_id', $userId)
            ->whereNull('topic_id')
            ->whereNotNull('topic')
            ->select('topic', DB::raw('COUNT(*) as conversations'), DB::raw('SUM(messages_count) as messages'))
            ->groupBy('topic')
            ->orderByDesc('conversations')
            ->limit(5)
            ->get()
            ->map(fn ($c) => [
                'topic_id'       => null,
                'title'          => $c->topic,
                'level'          => '-',
                'icon'           => '💬',
                'conversations'  => (int) $c->conversations,
                'total_messages' => (int) $c->messages,
            ]);

        // Total de conversas sem tópico definido
        $noTopicCount = AiConversation::where('user_id', $userId)
            ->whereNull('topic_id')
            ->whereNull('topic')
            ->count();

        return [
            'structured_topics' => $topicsWithId->values()->toArray(),
            'free_topics'       => $freeTopics->values()->toArray(),
            'no_topic_count'    => $noTopicCount,
            'most_practiced'    => $topicsWithId->first()['title'] ?? 'Nenhum ainda',
        ];
    }

    // ─── Estatísticas de uso de IA ────────────────────────────────────────────

    /**
     * Consumo de tokens e custo estimado da API OpenAI do usuário.
     */
    public function getAiUsageStats(User $user): array
    {
        $userId = $user->id;

        // Totais globais
        $allTime = AiUsageLog::where('user_id', $userId);

        $totalRequests        = (clone $allTime)->count();
        $totalTokens          = (int)   (clone $allTime)->sum('total_tokens');
        $totalCost            = (float) round((clone $allTime)->sum('estimated_cost'), 6);
        $totalPromptTokens    = (int)   (clone $allTime)->sum('prompt_tokens');
        $totalCompletionTokens= (int)   (clone $allTime)->sum('completion_tokens');

        // Este mês
        $thisMonth = AiUsageLog::where('user_id', $userId)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);

        $monthRequests = (clone $thisMonth)->count();
        $monthTokens   = (int)   (clone $thisMonth)->sum('total_tokens');
        $monthCost     = (float) round((clone $thisMonth)->sum('estimated_cost'), 6);

        // Esta semana
        $weekTokens = (int) AiUsageLog::where('user_id', $userId)
            ->where('created_at', '>=', now()->startOfWeek())
            ->sum('total_tokens');

        // Quebra por modelo
        $perModel = AiUsageLog::where('user_id', $userId)
            ->select('model', DB::raw('COUNT(*) as requests'), DB::raw('SUM(total_tokens) as tokens'), DB::raw('SUM(estimated_cost) as cost'))
            ->groupBy('model')
            ->orderByDesc('requests')
            ->get()
            ->map(fn ($r) => [
                'model'          => $r->model,
                'requests'       => (int)   $r->requests,
                'total_tokens'   => (int)   $r->tokens,
                'estimated_cost' => (float) round($r->cost, 6),
            ])
            ->toArray();

        // Transcrições de voz
        $voiceStats = AiVoiceMessage::where('user_id', $userId)
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw("COUNT(CASE WHEN transcription_driver = 'whisper-1' THEN 1 END) as whisper_count"),
                DB::raw("COUNT(CASE WHEN transcription_driver = 'mock' THEN 1 END) as mock_count"),
            )
            ->first();

        return [
            'all_time' => [
                'total_requests'         => $totalRequests,
                'total_tokens'           => $totalTokens,
                'prompt_tokens'          => $totalPromptTokens,
                'completion_tokens'      => $totalCompletionTokens,
                'estimated_cost_usd'     => $totalCost,
            ],
            'this_month' => [
                'total_requests'     => $monthRequests,
                'total_tokens'       => $monthTokens,
                'estimated_cost_usd' => $monthCost,
            ],
            'this_week' => [
                'total_tokens' => $weekTokens,
            ],
            'per_model'   => $perModel,
            'voice' => [
                'total_transcriptions' => (int) ($voiceStats->total     ?? 0),
                'via_whisper'          => (int) ($voiceStats->whisper_count ?? 0),
                'via_mock'             => (int) ($voiceStats->mock_count    ?? 0),
            ],
        ];
    }
}
