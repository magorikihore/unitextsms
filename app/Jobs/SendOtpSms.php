<?php

namespace App\Jobs;

use App\Jobs\Concerns\SendsQueuedSms;
use App\Jobs\Concerns\UsesSmsRateLimiter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendOtpSms implements ShouldQueue
{
    use Queueable;
    use SendsQueuedSms;
    use UsesSmsRateLimiter;

    public int $tries = 1;

    public function __construct(public int $messageId)
    {
        $this->onQueue('sms-otp');
    }

    public function middleware(): array
    {
        return [$this->smsRateLimitMiddleware('sms-otp')];
    }
}
