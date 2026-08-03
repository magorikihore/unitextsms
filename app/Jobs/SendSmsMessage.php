<?php

namespace App\Jobs;

use App\Enums\MessageCategory;
use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Backward-compatible dispatcher that routes to the correct priority job.
 */
class SendSmsMessage implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $messageId) {}

    public function handle(): void
    {
        $message = Message::query()->find($this->messageId);
        if (! $message) {
            return;
        }

        $job = match ($message->category) {
            MessageCategory::Otp => new SendOtpSms($message->id),
            MessageCategory::Marketing => new SendBulkSms($message->id),
            default => new SendTransactionalSms($message->id),
        };

        dispatch($job);
    }
}
