<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiCredential extends Model
{
    protected $fillable = [
        'client_id',
        'name',
        'key_id',
        'secret_hash',
        'secret_hint',
        'ip_allowlist',
        'sandbox',
        'is_active',
        'rate_limit_per_minute',
        'webhook_url',
        'webhook_signing_secret',
        'last_used_at',
        'revoked_at',
    ];

    protected $hidden = [
        'secret_hash',
        'webhook_signing_secret',
    ];

    protected function casts(): array
    {
        return [
            'ip_allowlist' => 'array',
            'sandbox' => 'boolean',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
