<?php

namespace App\Services\Sms;

use App\Models\Message;

class SmsGateway
{
    public function __construct(
        private readonly LogSmsProvider $logProvider,
        private readonly JasminHttpSmsProvider $jasminProvider,
    ) {}

    public function driver(?string $driver = null): SmsProviderContract
    {
        $driver ??= config('sms.driver', 'log');

        return match ($driver) {
            'jasmin' => $this->jasminProvider,
            default => $this->logProvider,
        };
    }

    public function send(Message $message): array
    {
        $driver = $message->provider?->driver ?? config('sms.driver', 'log');

        return $this->driver($driver)->send($message);
    }
}
