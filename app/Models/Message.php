<?php

namespace App\Models;

use App\Enums\MessageCategory;
use App\Enums\MessageStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'client_id',
        'campaign_id',
        'sender_id_id',
        'contact_id',
        'sms_provider_id',
        'created_by',
        'api_credential_id',
        'to_phone',
        'to_phone_normalized',
        'from_sender',
        'body',
        'category',
        'status',
        'queue_name',
        'units',
        'client_charge',
        'provider_cost',
        'provider_message_id',
        'failure_reason',
        'network',
        'country_code',
        'queued_at',
        'expires_at',
        'otp_reference',
        'submitted_at',
        'delivered_at',
        'failed_at',
        'delivery_ms',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'category' => MessageCategory::class,
            'status' => MessageStatus::class,
            'client_charge' => 'decimal:4',
            'provider_cost' => 'decimal:4',
            'queued_at' => 'datetime',
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Message $message): void {
            if (empty($message->uuid)) {
                $message->uuid = (string) Str::uuid();
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function senderId(): BelongsTo
    {
        return $this->belongsTo(SenderId::class, 'sender_id_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(SmsProvider::class, 'sms_provider_id');
    }
}
