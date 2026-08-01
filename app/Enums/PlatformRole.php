<?php

namespace App\Enums;

enum PlatformRole: string
{
    case Owner = 'owner';
    case Administrator = 'administrator';
    case Finance = 'finance';
    case Support = 'support';
    case Auditor = 'auditor';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Platform Owner',
            self::Administrator => 'Administrator',
            self::Finance => 'Finance',
            self::Support => 'Support',
            self::Auditor => 'Auditor',
        };
    }

    public function canManageClients(): bool
    {
        return in_array($this, [self::Owner, self::Administrator, self::Support], true);
    }

    public function canManageCredits(): bool
    {
        return in_array($this, [self::Owner, self::Administrator, self::Finance], true);
    }
}
