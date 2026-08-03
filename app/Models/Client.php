<?php

namespace App\Models;

use App\Enums\BillingType;
use App\Enums\ClientStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'company_name',
        'trading_name',
        'registration_number',
        'contact_name',
        'contact_email',
        'contact_phone',
        'address',
        'city',
        'country',
        'status',
        'billing_type',
        'sms_balance',
        'credit_limit',
        'price_per_sms',
        'low_balance_threshold',
        'allowed_countries',
        'allowed_networks',
        'can_send_otp',
        'can_send_transactional',
        'can_send_marketing',
        'api_enabled',
        'daily_send_limit',
        'monthly_send_limit',
        'default_provider_id',
        'default_sender_id_id',
        'kyc_documents',
        'notes',
        'approved_at',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ClientStatus::class,
            'billing_type' => BillingType::class,
            'sms_balance' => 'decimal:2',
            'credit_limit' => 'decimal:2',
            'price_per_sms' => 'decimal:4',
            'low_balance_threshold' => 'decimal:2',
            'allowed_countries' => 'array',
            'allowed_networks' => 'array',
            'can_send_otp' => 'boolean',
            'can_send_transactional' => 'boolean',
            'can_send_marketing' => 'boolean',
            'api_enabled' => 'boolean',
            'kyc_documents' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Client $client): void {
            if (empty($client->uuid)) {
                $client->uuid = (string) Str::uuid();
            }
        });
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'is_active'])
            ->withTimestamps();
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(CreditLedgerEntry::class);
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }

    public function senderIds(): HasMany
    {
        return $this->hasMany(SenderId::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function contactGroups(): HasMany
    {
        return $this->hasMany(ContactGroup::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function apiCredentials(): HasMany
    {
        return $this->hasMany(ApiCredential::class);
    }

    public function defaultProvider(): BelongsTo
    {
        return $this->belongsTo(SmsProvider::class, 'default_provider_id');
    }

    public function defaultSenderId(): BelongsTo
    {
        return $this->belongsTo(SenderId::class, 'default_sender_id_id');
    }

    public function isActive(): bool
    {
        return $this->status === ClientStatus::Active;
    }

    public function availableBalance(): float
    {
        if ($this->billing_type === BillingType::Postpaid) {
            return (float) $this->credit_limit + (float) $this->sms_balance;
        }

        return (float) $this->sms_balance;
    }
}
