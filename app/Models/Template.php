<?php

namespace App\Models;

use App\Enums\MessageCategory;
use App\Enums\TemplateStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Template extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'name',
        'category',
        'language',
        'body',
        'variables',
        'status',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'category' => MessageCategory::class,
            'status' => TemplateStatus::class,
            'variables' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function render(array $vars = []): string
    {
        $body = $this->body;

        foreach ($vars as $key => $value) {
            $body = str_replace('{{'.$key.'}}', (string) $value, $body);
        }

        return $body;
    }
}
