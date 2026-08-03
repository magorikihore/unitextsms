<?php

namespace App\Support;

use App\Enums\MessageCategory;
use App\Jobs\SendBulkSms;
use App\Jobs\SendOtpSms;
use App\Jobs\SendTransactionalSms;
use App\Models\Message;

class SmsJobFactory
{
    public static function dispatch(Message $message): void
    {
        $job = match ($message->category) {
            MessageCategory::Otp => new SendOtpSms($message->id),
            MessageCategory::Marketing => new SendBulkSms($message->id),
            default => new SendTransactionalSms($message->id),
        };

        // Always wait for the surrounding DB transaction to commit. Otherwise a
        // fast Redis worker can pick up the job before the message row is visible
        // and exit as DONE while status stays "queued" forever.
        dispatch($job)->afterCommit();
    }
}
