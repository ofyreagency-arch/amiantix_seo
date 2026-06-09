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

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'image_model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-1'),
        'request_timeout' => env('OPENAI_REQUEST_TIMEOUT', 180),
        'connect_timeout' => env('OPENAI_CONNECT_TIMEOUT', 30),
        'retry_attempts' => env('OPENAI_RETRY_ATTEMPTS', 3),
        'retry_delay_ms' => env('OPENAI_RETRY_DELAY_MS', 2000),
        'max_output_tokens' => env('OPENAI_MAX_OUTPUT_TOKENS', 8192),
    ],

    'google_search_console' => [
        'enabled' => env('SEARCH_CONSOLE_ENABLED', true),
        'site_url' => env('GOOGLE_SEARCH_CONSOLE_SITE_URL', ''),
        'credentials' => env('GOOGLE_APPLICATION_CREDENTIALS', storage_path('google/service-account.json')),
        'access_token' => env('GOOGLE_SEARCH_CONSOLE_ACCESS_TOKEN'),
    ],

    'seo_engine' => [
        'admin_token'          => env('SEO_ENGINE_ADMIN_TOKEN', ''),
        'admin_web_password'   => env('ADMIN_WEB_PASSWORD', ''),
    ],

];
