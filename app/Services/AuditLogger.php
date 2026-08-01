<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public function log(
        string $action,
        ?Model $auditable = null,
        ?array $old = null,
        ?array $new = null,
        ?User $user = null,
        ?int $clientId = null,
    ): AuditLog {
        $user ??= auth()->user();

        return AuditLog::query()->create([
            'user_id' => $user?->id,
            'client_id' => $clientId,
            'action' => $action,
            'auditable_type' => $auditable ? $auditable::class : null,
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
