<?php

namespace App\Services\Sms;

use App\Models\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogSmsProvider implements SmsProviderContract
{
    public function send(Message $message): array
    {
        $id = 'LOG-'.Str::upper(Str::random(10));

        Log::info('SMS sent via log provider', [
            'message_uuid' => $message->uuid,
            'to' => $message->to_phone_normalized,
            'from' => $message->from_sender,
            'body' => $message->body,
            'provider_message_id' => $id,
        ]);

        return [
            'success' => true,
            'provider_message_id' => $id,
        ];
    }
}
