<?php

namespace App\Enums;

enum MessageCategory: string
{
    case Otp = 'otp';
    case Transactional = 'transactional';
    case Alert = 'alert';
    case Marketing = 'marketing';

    public function label(): string
    {
        return match ($this) {
            self::Otp => 'OTP',
            self::Transactional => 'Transactional',
            self::Alert => 'Alert',
            self::Marketing => 'Bulk SMS',
        };
    }

    /**
     * Accept panel/API aliases such as bulksms → marketing.
     */
    public static function normalize(string $value): self
    {
        $value = strtolower(trim($value));

        return match ($value) {
            'bulksms', 'bulk', 'bulk_sms', 'bulk-sms' => self::Marketing,
            'trxn', 'txn', 'transaction' => self::Transactional,
            default => self::from($value),
        };
    }

    public function queue(): string
    {
        return match ($this) {
            self::Otp => 'sms-otp',
            self::Transactional, self::Alert => 'sms-transactional',
            self::Marketing => 'sms-bulk',
        };
    }

    public function rateLimiterName(): string
    {
        return $this->queue();
    }

    public function priority(): int
    {
        return match ($this) {
            self::Otp => 1,
            self::Transactional, self::Alert => 2,
            self::Marketing => 3,
        };
    }

    public function isOtp(): bool
    {
        return $this === self::Otp;
    }

    public function isBulk(): bool
    {
        return $this === self::Marketing;
    }
}
