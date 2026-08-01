<?php

namespace App\Enums;

enum MessageCategory: string
{
    case Otp = 'otp';
    case Transactional = 'transactional';
    case Alert = 'alert';
    case Marketing = 'marketing';

    public function queue(): string
    {
        return match ($this) {
            self::Otp => 'sms-otp',
            self::Transactional => 'sms-transactional',
            self::Alert => 'sms-alerts',
            self::Marketing => 'sms-bulk',
        };
    }

    public function priority(): int
    {
        return match ($this) {
            self::Otp => 1,
            self::Transactional => 2,
            self::Alert => 3,
            self::Marketing => 4,
        };
    }
}
