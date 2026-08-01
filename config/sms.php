<?php

return [
    'driver' => env('SMS_PROVIDER_DRIVER', 'log'),

    'default_rate_limit' => (int) env('SMS_DEFAULT_RATE_LIMIT', 18),

    'otp_latency_pause_ms' => (int) env('SMS_OTP_LATENCY_PAUSE_MS', 3000),

    'queues' => [
        'otp' => 'sms-otp',
        'transactional' => 'sms-transactional',
        'alerts' => 'sms-alerts',
        'bulk' => 'sms-bulk',
    ],

    'jasmin' => [
        'base_url' => env('JASMIN_BASE_URL'),
        'username' => env('JASMIN_USERNAME'),
        'password' => env('JASMIN_PASSWORD'),
        'dlr_url' => env('JASMIN_DLR_URL'),
    ],

    'marketing_hours' => [
        'start' => env('SMS_MARKETING_HOURS_START', '08:00'),
        'end' => env('SMS_MARKETING_HOURS_END', '20:00'),
        'timezone' => env('SMS_MARKETING_TIMEZONE', 'Africa/Dar_es_Salaam'),
    ],
];
