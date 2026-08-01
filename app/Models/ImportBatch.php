<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportBatch extends Model
{
    protected $fillable = [
        'client_id',
        'user_id',
        'filename',
        'type',
        'total_rows',
        'imported',
        'duplicates',
        'invalid',
        'status',
        'summary',
    ];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
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
