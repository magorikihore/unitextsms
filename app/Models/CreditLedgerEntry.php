<?php

namespace App\Models;

use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CreditLedgerEntry extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'client_id',
        'type',
        'payment_method',
        'amount',
        'balance_before',
        'balance_after',
        'reference',
        'reason',
        'related_type',
        'related_id',
        'created_by',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'type' => LedgerEntryType::class,
            'payment_method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
