<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'phone',
        'phone_normalized',
        'first_name',
        'last_name',
        'email',
        'custom_fields',
        'tags',
        'consent_status',
        'is_opted_out',
        'is_valid',
    ];

    protected function casts(): array
    {
        return [
            'custom_fields' => 'array',
            'tags' => 'array',
            'is_opted_out' => 'boolean',
            'is_valid' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(ContactGroup::class);
    }
}
