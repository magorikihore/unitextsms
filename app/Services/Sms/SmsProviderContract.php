<?php

namespace App\Services\Sms;

use App\Models\Message;

interface SmsProviderContract
{
    /**
     * @return array{success: bool, provider_message_id?: string|null, error?: string|null}
     */
    public function send(Message $message): array;
}
