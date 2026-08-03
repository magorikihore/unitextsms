<?php

namespace App\Jobs\Concerns;

use App\Enums\CampaignStatus;
use App\Enums\MessageCategory;
use App\Enums\MessageStatus;
use App\Models\Campaign;
use App\Models\Message;
use App\Services\Sms\SmsGateway;
use App\Services\SmsCapacityGuard;
use App\Support\SmsStatusPresenter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait SendsQueuedSms
{
    public function handle(SmsGateway $gateway, SmsCapacityGuard $capacity): void
    {
        $message = Message::query()->with(['provider', 'campaign', 'client'])->find($this->messageId);

        if (! $message) {
            // Likely dispatched before DB commit — retry shortly.
            if (method_exists($this, 'attempts') && $this->attempts() < ($this->tries ?? 3)) {
                $this->release(1);

                return;
            }

            Log::warning(static::class.' message missing after retries', ['message_id' => $this->messageId]);

            return;
        }

        if ($message->status !== MessageStatus::Queued) {
            return;
        }

        if ($message->expires_at && $message->expires_at->isPast()) {
            $message->update([
                'status' => MessageStatus::Expired,
                'failure_reason' => 'OTP/message expired before send',
                'failed_at' => now(),
            ]);
            $this->bumpCampaign($message, failed: true);

            return;
        }

        if ($message->campaign && in_array($message->campaign->status, [CampaignStatus::Paused, CampaignStatus::Cancelled], true)) {
            $message->update([
                'status' => MessageStatus::Cancelled,
                'failure_reason' => 'Campaign '.$message->campaign->status->value,
            ]);

            return;
        }

        if ($message->category?->isBulk() && $capacity->shouldPauseBulk()) {
            $this->release(5);

            return;
        }

        $result = $gateway->send($message);

        if ($result['success'] ?? false) {
            $message->update([
                'status' => MessageStatus::Submitted,
                'provider_message_id' => $this->safeProviderMessageId($result['provider_message_id'] ?? null),
                'submitted_at' => now(),
            ]);

            if (($message->provider?->driver ?? config('sms.driver')) === 'log') {
                $message->update([
                    'status' => MessageStatus::Delivered,
                    'delivered_at' => now(),
                    'delivery_ms' => 100,
                ]);

                $this->bumpCampaign($message, delivered: true);
                $this->trackOtpLatency($message, 100);
                $capacity->recordOtpOutcome($message, success: true, latencyMs: 100);
            } else {
                $this->bumpCampaign($message, sent: true);
                $capacity->recordOtpOutcome($message, success: true);
            }
        } else {
            $rawError = $result['error'] ?? 'Send failed';
            $message->update([
                'status' => MessageStatus::Failed,
                'failure_reason' => $this->safeFailureReason(SmsStatusPresenter::humanize($rawError) ?: $rawError),
                'failed_at' => now(),
            ]);
            $this->bumpCampaign($message, failed: true);
            $capacity->recordOtpOutcome($message, success: false);
        }
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
        Log::error(static::class.' failed', [
            'message_id' => $this->messageId,
            'error' => $e->getMessage(),
        ]);

        try {
            Message::query()->whereKey($this->messageId)->where('status', MessageStatus::Queued)->update([
                'status' => MessageStatus::Failed,
                'failure_reason' => $this->safeFailureReason($e->getMessage()),
                'failed_at' => now(),
            ]);
        } catch (\Throwable $updateError) {
            Log::error(static::class.' could not mark message failed', [
                'message_id' => $this->messageId,
                'error' => $updateError->getMessage(),
            ]);
        }
    }

    protected function safeProviderMessageId(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $id = trim((string) $value);

        if ($id === '') {
            return null;
        }

        return mb_substr($id, 0, 191);
    }

    protected function safeFailureReason(mixed $value): string
    {
        $reason = trim((string) $value);

        if ($reason === '') {
            return 'Send failed';
        }

        // Keep well under varchar(255) / text index limits and avoid storing full SQL dumps.
        return mb_substr($reason, 0, 240);
    }
}
