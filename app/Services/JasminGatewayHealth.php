<?php

namespace App\Services;

use App\Enums\MessageStatus;
use App\Models\Message;
use App\Models\SystemAlert;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JasminGatewayHealth
{
    public const CACHE_KEY = 'sms:jasmin:health';

    public const ALERT_TYPE = 'gateway';

    /**
     * @return array{
     *     healthy: bool,
     *     status: string,
     *     http_ok: bool,
     *     http_ms: int|null,
     *     http_error: string|null,
     *     last_delivered_at: string|null,
     *     last_delivered_age_seconds: int|null,
     *     last_submitted_at: string|null,
     *     recent_gateway_failures: int,
     *     checked_at: string
     * }
     */
    public function snapshot(bool $force = false): array
    {
        if (! $force) {
            $cached = Cache::get(self::CACHE_KEY);
            if (is_array($cached)) {
                return $cached;
            }
        }

        return $this->check();
    }

    /**
     * @return array{
     *     healthy: bool,
     *     status: string,
     *     http_ok: bool,
     *     http_ms: int|null,
     *     http_error: string|null,
     *     last_delivered_at: string|null,
     *     last_delivered_age_seconds: int|null,
     *     last_submitted_at: string|null,
     *     recent_gateway_failures: int,
     *     checked_at: string
     * }
     */
    public function check(): array
    {
        $baseUrl = rtrim((string) (config('sms.jasmin.base_url') ?: ''), '/');
        $httpOk = false;
        $httpMs = null;
        $httpError = null;

        if ($baseUrl === '') {
            $httpError = 'JASMIN_BASE_URL is not configured';
        } else {
            $started = microtime(true);
            try {
                $response = Http::timeout(5)->get($baseUrl.'/');
                $httpMs = (int) round((microtime(true) - $started) * 1000);
                $httpOk = $response->successful();
                if (! $httpOk) {
                    $httpError = 'HTTP '.$response->status();
                }
            } catch (\Throwable $e) {
                $httpMs = (int) round((microtime(true) - $started) * 1000);
                $httpError = $e->getMessage();
            }
        }

        $lastDelivered = Message::query()
            ->where('status', MessageStatus::Delivered)
            ->whereNotNull('delivered_at')
            ->latest('delivered_at')
            ->value('delivered_at');

        $lastSubmitted = Message::query()
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->value('submitted_at');

        $deliveredAt = $lastDelivered ? \Illuminate\Support\Carbon::parse($lastDelivered) : null;
        $submittedAt = $lastSubmitted ? \Illuminate\Support\Carbon::parse($lastSubmitted) : null;

        $recentGatewayFailures = Message::query()
            ->where('status', MessageStatus::Failed)
            ->where('failed_at', '>=', now()->subHour())
            ->where(function ($q) {
                $q->where('failure_reason', 'like', '%Gateway%')
                    ->orWhere('failure_reason', 'like', '%Jasmin%')
                    ->orWhere('failure_reason', 'like', '%PRECONDITION_FAILED%')
                    ->orWhere('failure_reason', 'like', '%ChannelClosed%');
            })
            ->count();

        $healthy = $httpOk && $recentGatewayFailures < 5;
        $status = $healthy ? 'ok' : ($httpOk ? 'degraded' : 'down');

        $snapshot = [
            'healthy' => $healthy,
            'status' => $status,
            'http_ok' => $httpOk,
            'http_ms' => $httpMs,
            'http_error' => $httpError,
            'last_delivered_at' => $deliveredAt?->toIso8601String(),
            'last_delivered_age_seconds' => $deliveredAt ? $deliveredAt->diffInSeconds(now()) : null,
            'last_submitted_at' => $submittedAt?->toIso8601String(),
            'recent_gateway_failures' => $recentGatewayFailures,
            'checked_at' => now()->toIso8601String(),
        ];

        Cache::put(self::CACHE_KEY, $snapshot, now()->addMinutes(2));
        $this->syncAlert($snapshot);

        return $snapshot;
    }

    public function recordGatewayFailure(string $reason): void
    {
        Cache::put('sms:jasmin:last_failure', [
            'reason' => mb_substr($reason, 0, 240),
            'at' => now()->toIso8601String(),
        ], now()->addHour());

        // Force a fresh check soon so the admin panel reflects the incident.
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    protected function syncAlert(array $snapshot): void
    {
        $open = SystemAlert::query()
            ->where('type', self::ALERT_TYPE)
            ->where('is_resolved', false)
            ->latest('id')
            ->first();

        if ($snapshot['healthy']) {
            if ($open) {
                $open->update([
                    'is_resolved' => true,
                    'resolved_at' => now(),
                ]);
            }

            return;
        }

        $title = $snapshot['http_ok']
            ? 'Jasmin gateway degraded'
            : 'Jasmin gateway unreachable';

        $body = $snapshot['http_ok']
            ? sprintf(
                '%d gateway-related failures in the last hour. Last delivery %s.',
                (int) $snapshot['recent_gateway_failures'],
                $snapshot['last_delivered_at'] ?? 'never'
            )
            : sprintf(
                'HTTP probe failed (%s). SMS submits to Tigo may be stalled.',
                $snapshot['http_error'] ?? 'unknown error'
            );

        if ($open) {
            $open->update([
                'severity' => $snapshot['http_ok'] ? 'warning' : 'critical',
                'title' => $title,
                'body' => $body,
                'meta' => $snapshot,
            ]);

            return;
        }

        SystemAlert::query()->create([
            'type' => self::ALERT_TYPE,
            'severity' => $snapshot['http_ok'] ? 'warning' : 'critical',
            'title' => $title,
            'body' => $body,
            'is_resolved' => false,
            'meta' => $snapshot,
        ]);

        Log::warning('Jasmin gateway health alert opened', $snapshot);
    }
}
