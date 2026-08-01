<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Enums\MessageCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'created_by',
        'sender_id_id',
        'template_id',
        'name',
        'status',
        'category',
        'message_body',
        'scheduled_at',
        'started_at',
        'completed_at',
        'total_recipients',
        'valid_count',
        'invalid_count',
        'duplicate_count',
        'opted_out_count',
        'queued_count',
        'sent_count',
        'delivered_count',
        'failed_count',
        'estimated_units',
        'estimated_cost',
        'charged_amount',
        'content_hash',
        'source_meta',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'category' => MessageCategory::class,
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'estimated_cost' => 'decimal:2',
            'charged_amount' => 'decimal:2',
            'source_meta' => 'array',
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

    public function senderId(): BelongsTo
    {
        return $this->belongsTo(SenderId::class, 'sender_id_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function deliveryRate(): float
    {
        if ($this->sent_count === 0) {
            return 0;
        }

        return round(($this->delivered_count / max($this->sent_count, 1)) * 100, 2);
    }
}
