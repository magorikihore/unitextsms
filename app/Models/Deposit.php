<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Deposit extends Model
{
    protected $fillable = [
        'client_id',
        'user_id',
        'uuid',
        'reference',
        'request_ref',
        'operator_ref',
        'receipt_number',
        'phone',
        'operator',
        'amount',
        'currency',
        'credits',
        'status',
        'failure_reason',
        'completed_at',
        'failed_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'credits' => 'decimal:2',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Deposit $deposit) {
            $deposit->uuid ??= (string) Str::uuid();
            $deposit->reference ??= 'DEP-'.Str::upper(Str::random(12));
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isFinal(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'expired', 'rejected'], true);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
