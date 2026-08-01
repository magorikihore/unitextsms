<?php

namespace App\Models;

use App\Enums\MessageCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsRoute extends Model
{
    protected $fillable = [
        'sms_provider_id',
        'name',
        'category',
        'country_code',
        'network',
        'priority',
        'is_active',
        'is_failover',
    ];

    protected function casts(): array
    {
        return [
            'category' => MessageCategory::class,
            'is_active' => 'boolean',
            'is_failover' => 'boolean',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(SmsProvider::class, 'sms_provider_id');
    }
}
