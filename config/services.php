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

    /*
    | Python NLP microservice that scores dispute reports (sentiment, confidence,
    | suggested resolution). When disabled or unreachable, disputes are still
    | recorded and simply wait for a human moderator.
    */
    'dispute_ai' => [
        'enabled' => (bool) env('AI_MODERATION_ENABLED', true),
        'url' => rtrim((string) env('AI_MODERATION_URL', 'http://127.0.0.1:8000'), '/'),
        'timeout' => (int) env('AI_MODERATION_TIMEOUT', 3),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
