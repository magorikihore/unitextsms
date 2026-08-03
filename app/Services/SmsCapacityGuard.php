<?php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Enums\MessageCategory;
use App\Models\Campaign;
use App\Models\Message;
use Illuminate\Support\Facades\Cache;

class SmsCapacityGuard
{
    public function shouldPauseBulk(): bool
    {
        if (Cache::get('sms:bulk:paused')) {
            return true;
        }

        $latency = (int) Cache::get('sms:otp:avg_latency_ms', 0);
        $threshold = (int) config('sms.otp_latency_pause_ms', 3000);

        if ($latency > $threshold) {
            $this->pauseBulk('OTP latency '.$latency.'ms exceeded threshold');

            return true;
        }

        $window = Cache::get('sms:otp:outcomes', ['success' => 0, 'failed' => 0]);
        $total = (int) $window['success'] + (int) $window['failed'];
        if ($total >= 10) {
            $failRate = (int) $window['failed'] / $total;
            if ($failRate >= (float) config('sms.otp_failure_pause_rate', 0.25)) {
                $this->pauseBulk('OTP failure rate '.round($failRate * 100, 1).'%');

                return true;
            }
        }

        return false;
    }

    public function pauseBulk(string $reason): void
    {
        Cache::put('sms:bulk:paused', $reason, now()->addMinutes(2));

        Campaign::query()
            ->where('status', CampaignStatus::Processing)
            ->where('category', MessageCategory::Marketing)
            ->update(['status' => CampaignStatus::Paused]);
    }

    public function resumeBulkIfHealthy(): void
    {
        if (! $this->shouldPauseBulk()) {
            Cache::forget('sms:bulk:paused');
        }
    }

    public function recordOtpOutcome(Message $message, bool $success, ?int $latencyMs = null): void
    {
        if ($message->category !== MessageCategory::Otp) {
            return;
        }

        $window = Cache::get('sms:otp:outcomes', ['success' => 0, 'failed' => 0]);
        $window[$success ? 'success' : 'failed'] = ((int) $window[$success ? 'success' : 'failed']) + 1;
        Cache::put('sms:otp:outcomes', $window, now()->addMinutes(10));

        if ($latencyMs !== null) {
            $prev = (int) Cache::get('sms:otp:avg_latency_ms', $latencyMs);
            Cache::put('sms:otp:avg_latency_ms', (int) (($prev + $latencyMs) / 2), now()->addMinutes(10));
        }

        if ($success) {
            $this->resumeBulkIfHealthy();
        }
    }
}
