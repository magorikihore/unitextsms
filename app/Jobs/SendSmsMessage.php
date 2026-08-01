<?php

namespace App\Jobs;

use App\Enums\CampaignStatus;
use App\Enums\MessageCategory;
use App\Enums\MessageStatus;
use App\Models\Campaign;
use App\Models\Message;
use App\Services\Sms\SmsGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendSmsMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $messageId) {}

    public function handle(SmsGateway $gateway): void
    {
        $message = Message::query()->with(['provider', 'campaign', 'client'])->find($this->messageId);

        if (! $message) {
            return;
        }

        if ($message->status !== MessageStatus::Queued) {
            return;
        }

        if ($message->campaign && in_array($message->campaign->status, [CampaignStatus::Paused, CampaignStatus::Cancelled], true)) {
            $message->update([
                'status' => MessageStatus::Cancelled,
                'failure_reason' => 'Campaign '.$message->campaign->status->value,
            ]);

            return;
        }

        // Slow/pause bulk when OTP latency is high
        if ($message->category === MessageCategory::Marketing) {
            $otpLatency = (int) Cache::get('sms:otp:avg_latency_ms', 0);
            $threshold = (int) config('sms.otp_latency_pause_ms', 3000);

            if ($otpLatency > $threshold) {
                $this->release(5);

                return;
            }
        }

        $this->throttle($message);

        $result = $gateway->send($message);

        if ($result['success'] ?? false) {
            $message->update([
                'status' => MessageStatus::Submitted,
                'provider_message_id' => $result['provider_message_id'] ?? null,
                'submitted_at' => now(),
            ]);

            // Log driver auto-delivers for local/demo flows
            if (($message->provider?->driver ?? config('sms.driver')) === 'log') {
                $message->update([
                    'status' => MessageStatus::Delivered,
                    'delivered_at' => now(),
                    'delivery_ms' => 100,
                ]);

                $this->bumpCampaign($message, delivered: true);
                $this->trackOtpLatency($message, 100);
            } else {
                $this->bumpCampaign($message, sent: true);
            }
        } else {
            $message->update([
                'status' => MessageStatus::Failed,
                'failure_reason' => $result['error'] ?? 'Send failed',
                'failed_at' => now(),
            ]);
            $this->bumpCampaign($message, failed: true);
        }
    }

    protected function throttle(Message $message): void
    {
        $rate = $message->provider?->rate_limit_per_second
            ?? (int) config('sms.default_rate_limit', 18);

        $key = 'sms:throttle:'.($message->sms_provider_id ?? 'default');
        $window = (int) floor(microtime(true));
        $cacheKey = $key.':'.$window;
        $count = (int) Cache::get($cacheKey, 0);

        if ($count >= $rate) {
            $this->release(1);

            return;
        }

        Cache::put($cacheKey, $count + 1, 2);
    }

    protected function bumpCampaign(Message $message, bool $sent = false, bool $delivered = false, bool $failed = false): void
    {
        if (! $message->campaign_id) {
            return;
        }

        $campaign = Campaign::query()->whereKey($message->campaign_id)->lockForUpdate()->first();
        if (! $campaign) {
            return;
        }

        if ($sent || $delivered) {
            $campaign->sent_count++;
        }
        if ($delivered) {
            $campaign->delivered_count++;
        }
        if ($failed) {
            $campaign->failed_count++;
        }

        $processed = $campaign->sent_count + $campaign->failed_count;
        if ($processed >= $campaign->queued_count && $campaign->status === CampaignStatus::Processing) {
            $campaign->status = CampaignStatus::Completed;
            $campaign->completed_at = now();
        }

        $campaign->save();
    }

    protected function trackOtpLatency(Message $message, int $ms): void
    {
        if ($message->category !== MessageCategory::Otp) {
            return;
        }

        $prev = (int) Cache::get('sms:otp:avg_latency_ms', $ms);
        Cache::put('sms:otp:avg_latency_ms', (int) (($prev + $ms) / 2), now()->addMinutes(10));
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendSmsMessage failed', ['message_id' => $this->messageId, 'error' => $e->getMessage()]);

        Message::query()->whereKey($this->messageId)->update([
            'status' => MessageStatus::Failed,
            'failure_reason' => $e->getMessage(),
            'failed_at' => now(),
        ]);
    }
}
