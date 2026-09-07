<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'mini_erp_ai' => [
        'enabled' => (bool) env('MINI_ERP_AI_ENABLED', true),
        'service_url' => env('MINI_ERP_AI_SERVICE_URL', 'https://mini-erp-ai.a2zenon.com'),
        'secret' => env('MINI_ERP_AI_APP_SECRET'),
        'require_secret' => (bool) env('MINI_ERP_AI_REQUIRE_SECRET', true),
        'browser_api_url' => env('MINI_ERP_AI_BROWSER_API_URL', '/api/mini-erp-ai'),
        'timeout_seconds' => (int) env('MINI_ERP_AI_TIMEOUT_SECONDS', 90),
        'voice_enabled' => (bool) env('MINI_ERP_AI_VOICE_ENABLED', false),
        'vision_enabled' => (bool) env('MINI_ERP_AI_VISION_ENABLED', true),
    ],

];
