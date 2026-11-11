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

    'vonage' => [
        'application_id' => env('VONAGE_APPLICATION_ID'),
        'private_key' => env('VONAGE_PRIVATE_KEY'),
        'vonage_number' => env('VONAGE_NUMBER'),
        'answerphone_path' => env('VONAGE_ANSWERPHONE_PATH'),
        'answerphone_filename' => env('VONAGE_ANSWERPHONE_FILENAME'),
        'answerphone_enabled' => (bool) env('VONAGE_ANSWERPHONE_ENABLED', false),
        'aws_access_key_id' => env('RECORDINGS_AWS_ACCESS_KEY_ID'),
        'aws_secret_access_key' => env('RECORDINGS_AWS_SECRET_ACCESS_KEY'),
        'aws_region' => env('RECORDINGS_AWS_REGION'),
        'aws_bucket' => env('RECORDINGS_AWS_BUCKET'),
        'public_url' => env('VONAGE_PUBLIC_URL'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
