<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitxtImportMap extends Model
{
    protected $fillable = [
        'source',
        'old_user_id',
        'old_customer_id',
        'old_email',
        'client_id',
        'user_id',
        'sender_ids_imported',
        'contacts_imported',
        'balance_imported',
        'meta',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'balance_imported' => 'decimal:2',
            'imported_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
