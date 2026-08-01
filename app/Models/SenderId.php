<?php

namespace App\Models;

use App\Enums\MessageCategory;
use App\Enums\SenderIdStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SenderId extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'sender',
        'status',
        'category',
        'provider_status',
        'regulator_status',
        'supporting_documents',
        'rejection_reason',
        'approved_at',
        'expires_at',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => SenderIdStatus::class,
            'category' => MessageCategory::class,
            'approved_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function isUsable(): bool
    {
        if ($this->status !== SenderIdStatus::Approved) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}
