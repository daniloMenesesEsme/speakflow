<?php

use App\Http\Controllers\API\AiController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ConversationTopicController;
use App\Http\Controllers\API\LearningAnalyticsController;
use App\Http\Controllers\API\AdaptiveTutorController;
use App\Http\Controllers\API\AiLessonController;
use App\Http\Controllers\API\MissionController;
use App\Http\Controllers\API\SubscriptionController;
use App\Http\Controllers\API\LearningController;
use App\Http\Controllers\API\LeaderboardController;
use App\Http\Controllers\API\LessonController;
use App\Http\Controllers\API\ExerciseController;
use App\Http\Controllers\API\PronunciationController;
use App\Http\Controllers\API\DialogueController;
use App\Http\Controllers\API\AchievementController;
use App\Http\Controllers\API\StudySessionController;
use App\Http\Controllers\API\LanguageController;
use App\Http\Controllers\API\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - SpeakFlow
|--------------------------------------------------------------------------
| Versão: v1
| Autenticação: JWT Bearer Token
*/

Route::prefix('v1')->group(function () {

    // ─── Autenticação (público) ───────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login',    [AuthController::class, 'login']);
    });

    // ─── Idiomas disponíveis (público) ────────────────────────────────────
    Route::get('languages',        [LanguageController::class, 'index']);
    Route::get('languages/{language}', [LanguageController::class, 'show']);

    // ─── Rotas autenticadas ───────────────────────────────────────────────
    Route::middleware('auth:api')->group(function () {

        // Auth
        Route::prefix('auth')->group(function () {
            Route::post('logout',          [AuthController::class, 'logout']);
            Route::post('refresh',         [AuthController::class, 'refresh']);
            Route::get('me',               [AuthController::class, 'me']);
            Route::put('profile',          [AuthController::class, 'updateProfile']);
            Route::put('change-password',  [AuthController::class, 'changePassword']);
        });

        // Dashboard
        Route::get('dashboard', [StudySessionController::class, 'dashboard']);

        // Estatísticas e conquistas do usuário
        Route::get('users/stats',        [UserController::class, 'stats']);
        Route::get('users/achievements', [UserController::class, 'achievements']);

        // Ranking semanal
        Route::prefix('leaderboard')->group(function () {
            Route::get('/',   [LeaderboardController::class, 'index']);
            Route::get('me',  [LeaderboardController::class, 'me']);
        });

        // Tutor Virtual com IA
        Route::prefix('ai')->group(function () {
            Route::post('chat',                         [AiController::class, 'chat']);
            Route::post('voice',                        [AiController::class, 'voice']);
            Route::get('voice',                         [AiController::class, 'voiceHistory']);
            Route::get('usage',                         [AiController::class, 'usage']);
            Route::get('corrections',                   [AiController::class, 'corrections']);
            Route::get('conversations',                 [AiController::class, 'conversations']);
            Route::get('conversations/{conversation}',  [AiController::class, 'history']);

            // Tutor Adaptativo
            Route::prefix('adaptive-plan')->group(function () {
                Route::get('/',          [AdaptiveTutorController::class, 'plan']);
                Route::get('level',      [AdaptiveTutorController::class, 'level']);
                Route::get('grammar',    [AdaptiveTutorController::class, 'grammar']);
                Route::get('topics',     [AdaptiveTutorController::class, 'topics']);
                Route::get('exercises',  [AdaptiveTutorController::class, 'exercises']);
            });

            // Gerador de Lições com IA
            Route::post('generate-lesson',       [AiLessonController::class, 'generate']);
            Route::get('lessons',                [AiLessonController::class, 'history']);
            Route::get('lessons/{id}',           [AiLessonController::class, 'show']);
        });

        // Lições
        Route::prefix('lessons')->group(function () {
            Route::get('/',                        [LessonController::class, 'index']);
            Route::get('recommended',              [LessonController::class, 'recommended']);
            Route::get('categories',               [LessonController::class, 'categories']);
            Route::get('{lesson}',                 [LessonController::class, 'show']);
            Route::post('{lesson}/complete',       [LessonController::class, 'completeLesson']);
        });

        // Exercícios
        Route::prefix('exercises')->group(function () {
            Route::get('/',                             [ExerciseController::class, 'index']);
            Route::get('{exercise}',                    [ExerciseController::class, 'show']);
            Route::post('lessons/{lesson}/start',       [ExerciseController::class, 'start']);
            Route::post('{exercise}/answer',            [ExerciseController::class, 'submitAnswer']);
            Route::post('sessions/{session}/complete',  [ExerciseController::class, 'complete']);
        });

        // Pronúncia
        Route::prefix('pronunciation')->group(function () {
            // Análise completa (ponto de entrada recomendado)
            Route::post('analyze',                      [PronunciationController::class, 'analyze']);
            // Calcula score composto sem persistir (preview)
            Route::post('calculate-score',              [PronunciationController::class, 'calculateScore']);
            // Endpoint legado — aceita score direto (mantido por compatibilidade)
            Route::post('score',                        [PronunciationController::class, 'score']);
            Route::get('analysis',                      [PronunciationController::class, 'analysis']);
            Route::get('phrases',                       [PronunciationController::class, 'phrasesForPractice']);
            Route::get('weekly-report',                 [PronunciationController::class, 'weeklyReport']);
            Route::get('history',                       [PronunciationController::class, 'history']);
            Route::get('phrases/{phrase}/progress',     [PronunciationController::class, 'phraseProgress']);
        });

        // Diálogos (catálogo)
        Route::prefix('dialogues')->group(function () {
            Route::get('/',                             [DialogueController::class, 'index']);
            Route::get('topics',                        [DialogueController::class, 'topics']);
            Route::get('random',                        [DialogueController::class, 'random']);
            Route::get('{dialogue}',                    [DialogueController::class, 'show']);
        });

        // Conversações (sessões de conversa)
        Route::prefix('conversations')->group(function () {
            Route::get('/',                                    [DialogueController::class, 'mySessions']);
            Route::post('start',                               [DialogueController::class, 'startConversation']);
            Route::post('validate',                            [DialogueController::class, 'validate']);
            Route::get('{session}/next-line',                  [DialogueController::class, 'nextLine']);
            Route::post('{session}/respond',                   [DialogueController::class, 'respond']);
            Route::post('{session}/complete',                  [DialogueController::class, 'complete']);
            Route::post('{session}/abandon',                   [DialogueController::class, 'abandon']);
            Route::get('{session}/history',                    [DialogueController::class, 'history']);
            Route::get('topics/{topic}/history',               [DialogueController::class, 'topicHistory']);
        });

        // Conquistas
        Route::prefix('achievements')->group(function () {
            Route::get('/',    [AchievementController::class, 'index']);
            Route::get('mine', [AchievementController::class, 'myAchievements']);
        });

        // Sessões de estudo
        Route::prefix('sessions')->group(function () {
            Route::get('/',               [StudySessionController::class, 'index']);
            Route::get('weekly-report',   [StudySessionController::class, 'weeklyReport']);
        });

        // Motor Pedagógico — LearningEngine
        Route::prefix('learning')->group(function () {
            Route::get('progress',                         [LearningController::class, 'progress']);
            Route::get('next-level-estimate',              [LearningController::class, 'nextLevelEstimate']);
            Route::get('next-lesson',                      [LearningController::class, 'nextLesson']);
            Route::get('phrases-for-review',               [LearningController::class, 'phrasesForReview']);
            Route::post('phrases/{phrase}/review',         [LearningController::class, 'processReview']);
            Route::get('cefr-levels',                      [LearningController::class, 'cefrLevels']);
        });

        // Tópicos de Conversa
        Route::prefix('conversation-topics')->group(function () {
            Route::get('/',         [ConversationTopicController::class, 'index']);
            Route::get('{conversationTopic}', [ConversationTopicController::class, 'show']);
        });

        // Learning Analytics
        Route::prefix('analytics')->group(function () {
            Route::get('/',         [LearningAnalyticsController::class, 'index']);
            Route::get('general',   [LearningAnalyticsController::class, 'general']);
            Route::get('learning',  [LearningAnalyticsController::class, 'learning']);
            Route::get('topics',    [LearningAnalyticsController::class, 'topics']);
            Route::get('ai-usage',  [LearningAnalyticsController::class, 'aiUsage']);
        });

        // Daily Missions
        Route::prefix('missions')->group(function () {
            Route::get('today',    [MissionController::class, 'today']);
            Route::get('progress', [MissionController::class, 'progress']);
            Route::get('check',    [MissionController::class, 'check']);
        });

        // Subscription & Plans
        Route::prefix('subscription')->group(function () {
            Route::get('/',        [SubscriptionController::class, 'show']);
            Route::get('plans',    [SubscriptionController::class, 'plans']);
            Route::post('upgrade', [SubscriptionController::class, 'upgrade']);
            Route::get('logs',     [SubscriptionController::class, 'logs']);
        });

        // Alias público para listagem de planos
        Route::get('plans', [SubscriptionController::class, 'plans']);
    });
});
