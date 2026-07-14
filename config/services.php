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

    'moodle' => [
        'base_url' => env('MOODLE_BASE_URL'),
        'token' => env('MOODLE_TOKEN'),
        'cache_ttl' => env('MOODLE_CACHE_TTL', 900),
        // Moodle role id used when enrolling (5 = student on a default install).
        'enrol_role_id' => env('MOODLE_ENROL_ROLE_ID', 5),
        // NEO auto-enrolment: course new staff are enrolled into on onboarding.
        // Leave unset to disable the rule (staff records are still stored).
        'neo_course_id' => env('MOODLE_NEO_COURSE_ID'),
        'neo_offset_months' => env('MOODLE_NEO_OFFSET_MONTHS', 4),
    ],

];
