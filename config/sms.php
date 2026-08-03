<?php

return [
    'driver' => env('SMS_PROVIDER_DRIVER', 'log'),

    /*
    | Combined provider ceiling (TPS). Queue budgets below must not exceed this.
    */
    'provider_tps' => (int) env('SMS_PROVIDER_TPS', 20),

    'default_rate_limit' => (int) env('SMS_DEFAULT_RATE_LIMIT', 18),

    'otp_latency_pause_ms' => (int) env('SMS_OTP_LATENCY_PAUSE_MS', 3000),

    'otp_failure_pause_rate' => (float) env('SMS_OTP_FAILURE_PAUSE_RATE', 0.25),

    'queues' => [
        'otp' => 'sms-otp',
        'transactional' => 'sms-transactional',
        'bulk' => 'sms-bulk',
    ],

    /*
    | Reserved TPS per queue (must sum <= provider_tps).
    */
    'rate_limits' => [
        'sms-otp' => (int) env('SMS_OTP_TPS', 10),
        'sms-transactional' => (int) env('SMS_TRANSACTIONAL_TPS', 6),
        'sms-bulk' => (int) env('SMS_BULK_TPS', 4),
    ],

    'otp' => [
        'ttl_minutes' => (int) env('SMS_OTP_TTL_MINUTES', 5),
        'max_tries' => (int) env('SMS_OTP_MAX_TRIES', 1),
        'per_phone_per_hour' => (int) env('SMS_OTP_PER_PHONE_HOUR', 5),
        'per_client_per_hour' => (int) env('SMS_OTP_PER_CLIENT_HOUR', 200),
        'per_ip_per_hour' => (int) env('SMS_OTP_PER_IP_HOUR', 30),
    ],

    'jasmin' => [
        'base_url' => env('JASMIN_BASE_URL'),
        'username' => env('JASMIN_USERNAME'),
        'password' => env('JASMIN_PASSWORD'),
        'dlr_url' => env('JASMIN_DLR_URL'),
    ],

    /*
    | Comma/space-separated IPs or CIDRs allowed to POST DLR callbacks.
    | In production an empty list rejects all DLR traffic (fail closed).
    */
    'dlr_allowed_ips' => array_values(array_filter(array_map(
        'trim',
        preg_split('/[\s,]+/', (string) env('JASMIN_DLR_ALLOWED_IPS', '')) ?: []
    ))),

    'marketing_hours' => [
        'start' => env('SMS_MARKETING_HOURS_START', '08:00'),
        'end' => env('SMS_MARKETING_HOURS_END', '20:00'),
        'timezone' => env('SMS_MARKETING_TIMEZONE', 'Africa/Dar_es_Salaam'),
    ],
];
