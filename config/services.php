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

    'open_library' => [
        'url' => env('OPEN_LIBRARY_URL', 'https://openlibrary.org'),
        'timeout' => (int) env('OPEN_LIBRARY_TIMEOUT', 5),
    ],

    'google_books' => [
        'url' => env('GOOGLE_BOOKS_URL', 'https://www.googleapis.com'),
        'key' => env('GOOGLE_BOOKS_KEY'),
        'timeout' => (int) env('GOOGLE_BOOKS_TIMEOUT', 5),
    ],

    'telegram' => [
        'url' => env('TELEGRAM_API_URL', 'https://api.telegram.org'),
        'token' => env('TELEGRAM_BOT_TOKEN'),
        // Optional: asked from Telegram / derived from APP_KEY when empty.
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'admin_id' => env('TELEGRAM_ADMIN_ID'),
        'timeout' => (int) env('TELEGRAM_TIMEOUT', 5),
    ],

    'wikidata' => [
        'url' => env('WIKIDATA_SPARQL_URL', 'https://query.wikidata.org/sparql'),
        'timeout' => (int) env('WIKIDATA_TIMEOUT', 90),
    ],

];
