<?php

namespace App\Providers;

use App\Contracts\SpeechRecognitionContract;
use App\Services\AchievementService;
use App\Services\AiTutorService;
use App\Services\ConversationEngine;
use App\Services\DailyActivityService;
use App\Services\LeaderboardService;
use App\Services\AdaptiveTutorService;
use App\Services\AiLessonGeneratorService;
use App\Services\DailyMissionService;
use App\Services\SubscriptionService;
use App\Services\AiCostAnalyticsService;
use App\Services\LearningAnalyticsService;
use App\Services\LearningEngine;
use App\Services\PlacementTestService;
use App\Services\PronunciationAnalyzer;
use App\Services\VoiceTranscriptionService;
use App\Services\Speech\Drivers\AzureSpeechDriver;
use App\Services\Speech\Drivers\MockSpeechRecognitionDriver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ─── Driver de reconhecimento de voz ────────────────────────────────
        // Seleciona o driver via variável de ambiente SPEECH_DRIVER.
        // Padrão: 'mock' (offline, sem dependências externas).
        // Para ativar Azure: definir SPEECH_DRIVER=azure no .env.
        $this->app->bind(SpeechRecognitionContract::class, function () {
            return match (config('services.speech.driver', 'mock')) {
                'azure'  => new AzureSpeechDriver(),
                default  => new MockSpeechRecognitionDriver(),
            };
        });

        // ─── Serviços de negócio ─────────────────────────────────────────────
        $this->app->singleton(PronunciationAnalyzer::class, function ($app) {
            return new PronunciationAnalyzer(
                $app->make(SpeechRecognitionContract::class)
            );
        });

        $this->app->singleton(LearningEngine::class);
        $this->app->singleton(ConversationEngine::class);
        $this->app->singleton(AchievementService::class);
        $this->app->singleton(DailyActivityService::class, function ($app) {
            return new DailyActivityService($app->make(AchievementService::class));
        });
        $this->app->singleton(LeaderboardService::class);
        $this->app->singleton(AiTutorService::class);
        $this->app->singleton(VoiceTranscriptionService::class);
        $this->app->singleton(LearningAnalyticsService::class);
        $this->app->singleton(DailyMissionService::class);
        $this->app->singleton(SubscriptionService::class);
        $this->app->singleton(AdaptiveTutorService::class);
        $this->app->singleton(AiLessonGeneratorService::class);
        $this->app->singleton(AiCostAnalyticsService::class);
        $this->app->singleton(PlacementTestService::class);
    }

    public function boot(): void
    {
        //
    }
}
