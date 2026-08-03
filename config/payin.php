<?php

return [
    'enabled' => (bool) env('PAYIN_ENABLED', false),
    'base_url' => rtrim(env('PAYIN_BASE_URL', 'https://api.sandbox.payin.co.tz/api/v1'), '/'),
    'api_key' => env('PAYIN_API_KEY'),
    'api_secret' => env('PAYIN_API_SECRET'),
    'webhook_secret' => env('PAYIN_WEBHOOK_SECRET'),
    'currency' => env('PAYIN_CURRENCY', 'TZS'),
    'min_amount' => (int) env('PAYIN_MIN_AMOUNT', 100),
    'max_amount' => (int) env('PAYIN_MAX_AMOUNT', 10000000),
    'poll_interval_seconds' => 5,
    'success_dismiss_seconds' => 60,
];
