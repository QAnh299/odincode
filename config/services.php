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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent'),
    ],

    'onesms' => [
        'username' => env('ONESMS_USERNAME'),
        'password' => env('ONESMS_PASSWORD'),
        'templates' => [
            'registration_success' => env('ONESMS_TEMPLATE_REGISTRATION', '123460'),
            'schedule_reminder' => env('ONESMS_TEMPLATE_SCHEDULE', '123457'),
            'online_exam' => env('ONESMS_TEMPLATE_EXAM', '123465'),
            'absent' => env('ONESMS_TEMPLATE_ABSENT', '123461'),
            'late' => env('ONESMS_TEMPLATE_LATE', '123462'),
            'assignment_deadline' => env('ONESMS_TEMPLATE_DEADLINE', '123463'),
            'assignment_result' => env('ONESMS_TEMPLATE_RESULT', '123458'),
            'payment_confirmation' => env('ONESMS_TEMPLATE_PAYMENT', '123459'),
            'schedule_change' => env('ONESMS_TEMPLATE_CHANGE', '123464'),
            'otp_reset' => env('ONESMS_TEMPLATE_OTP', '123456'),
        ],
    ],

];
