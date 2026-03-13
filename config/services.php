<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Speech Recognition — SpeakFlow
    |--------------------------------------------------------------------------
    | driver: 'mock'  → offline, baseado em similaridade textual (padrão)
    |         'azure' → Azure Cognitive Services (requer AZURE_SPEECH_KEY)
    |         'google'→ Google Cloud Speech-to-Text (futuro)
    |         'whisper'→ OpenAI Whisper (futuro)
    */
    'speech' => [
        'driver' => env('SPEECH_DRIVER', 'mock'),
    ],

    'azure_speech' => [
        'key'    => env('AZURE_SPEECH_KEY'),
        'region' => env('AZURE_SPEECH_REGION', 'eastus'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI — Tutor Virtual SpeakFlow
    |--------------------------------------------------------------------------
    | Deixe OPENAI_API_KEY vazio para usar o modo offline (fallback automático).
    | Modelo padrão: gpt-4o-mini (mais rápido e econômico).
    */
    'openai' => [
        'key'          => env('OPENAI_API_KEY'),
        'model'        => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'max_tokens'   => (int) env('OPENAI_MAX_TOKENS', 300),
        'organization' => env('OPENAI_ORGANIZATION'),
    ],

];
