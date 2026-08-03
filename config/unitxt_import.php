<?php

return [
    'source' => 'unitxt',

    'default_price_per_sms' => (float) env('UNITXT_IMPORT_DEFAULT_PRICE', 0.05),

    'default_provider_id' => env('UNITXT_IMPORT_DEFAULT_PROVIDER_ID'),

    /*
    | When old sms_unit is -1 (unlimited), do not invent a huge balance.
    | Import balance 0 and mark notes for manual review.
    */
    'unlimited_balance_as' => 0,

    'connection' => env('UNITXT_LEGACY_DB_CONNECTION', 'unitxt_legacy'),
];
