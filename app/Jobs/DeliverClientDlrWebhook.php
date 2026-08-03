<?php

namespace App\Jobs;

use App\Models\ApiCredential;
use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeliverClientDlrWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $credentialId,
        public array $payload,
    ) {
        $this->onQueue('sms-transactional');
    }

    public function backoff(): array
    {
        return [10, 30, 60, 120, 300];
    }

    public function handle(): void
    {
        $credential = ApiCredential::query()->find($this->credentialId);
        if (! $credential || ! $credential->is_active || ! $credential->webhook_url) {
            return;
        }

        $body = json_encode($this->payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', (string) $body, (string) $credential->webhook_signing_secret);

        $response = Http::timeout(10)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'User-Agent' => 'UniNText-Webhooks/1.0',
                'X-UniNText-Signature' => 'sha256='.$signature,
                'X-UniNText-Event' => (string) ($this->payload['event'] ?? 'sms.dlr'),
            ])
            ->withBody((string) $body, 'application/json')
            ->post($credential->webhook_url);

        if (! $response->successful()) {
            Log::warning('Client DLR webhook failed', [
                'credential_id' => $credential->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $response->throw();
        }
    }

    public static function dispatchForMessage(Message $message, ?string $providerStatus = null): void
    {
        $credential = null;
        if ($message->api_credential_id) {
            $credential = ApiCredential::query()
                ->whereKey($message->api_credential_id)
                ->where('is_active', true)
                ->whereNotNull('webhook_url')
                ->first();
        }

        if (! $credential) {
            $credential = ApiCredential::query()
                ->where('client_id', $message->client_id)
                ->where('is_active', true)
                ->whereNotNull('webhook_url')
                ->latest('id')
                ->first();
        }

        if (! $credential) {
            return;
        }

        self::dispatch($credential->id, [
            'event' => 'sms.dlr',
            'message_uuid' => $message->uuid,
            'to' => $message->to_phone_normalized,
            'from' => $message->from_sender,
            'status' => $message->status?->value,
            'provider_status' => $providerStatus,
            'units' => $message->units,
            'failure_reason' => $message->failure_reason,
            'submitted_at' => $message->submitted_at?->toIso8601String(),
            'delivered_at' => $message->delivered_at?->toIso8601String(),
            'failed_at' => $message->failed_at?->toIso8601String(),
            'delivery_ms' => $message->delivery_ms,
        ]);
    }
}
