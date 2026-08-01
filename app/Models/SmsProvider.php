<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmsProvider extends Model
{
    protected $fillable = [
        'name',
        'driver',
        'is_active',
        'rate_limit_per_second',
        'balance',
        'cost_per_sms',
        'credentials',
        'webhook_url',
        'avg_latency_ms',
        'last_health_check_at',
        'health_status',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'balance' => 'decimal:2',
            'cost_per_sms' => 'decimal:4',
            'credentials' => 'encrypted:array',
            'last_health_check_at' => 'datetime',
        ];
    }

    public function routes(): HasMany
    {
        return $this->hasMany(SmsRoute::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
