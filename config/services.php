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

    'open_meteo' => [
        'base_url' => env('OPEN_METEO_BASE_URL', 'https://api.open-meteo.com/v1/forecast'),
        'latitude' => (float) env('OPEN_METEO_LATITUDE', -26.18),
        'longitude' => (float) env('OPEN_METEO_LONGITUDE', -58.17),
        'timezone' => env('OPEN_METEO_TIMEZONE', env('CENTINELA_TIMEZONE', 'America/Argentina/Cordoba')),
        'timeout' => (int) env('OPEN_METEO_TIMEOUT', 10),
        'past_days' => (int) env('OPEN_METEO_PAST_DAYS', 7),
        'forecast_days' => (int) env('OPEN_METEO_FORECAST_DAYS', 1),
    ],

];
