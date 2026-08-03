<?php

namespace App\Services\PayIn;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PayInClient
{
    public function enabled(): bool
    {
        return (bool) config('payin.enabled')
            && filled(config('payin.api_key'))
            && filled(config('payin.api_secret'));
    }

    /**
     * @param  array{phone:string,amount:float|int,reference?:string,description?:string,callback_url?:string,operator?:string}  $payload
     * @return array<string, mixed>
     */
    public function collect(array $payload): array
    {
        $response = $this->http()
            ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
            ->post($this->url('/collection'), array_filter([
                'phone' => $payload['phone'],
                'amount' => $payload['amount'],
                'currency' => config('payin.currency', 'TZS'),
                'reference' => $payload['reference'] ?? null,
                'description' => $payload['description'] ?? null,
                'callback_url' => $payload['callback_url'] ?? null,
                'operator' => $payload['operator'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''));

        if (! $response->successful()) {
            throw new RuntimeException($this->errorMessage($response->json(), $response->body()));
        }

        return $response->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function status(string $requestRef): array
    {
        $response = $this->http()->get($this->url('/status/'.$requestRef));

        if (! $response->successful()) {
            throw new RuntimeException($this->errorMessage($response->json(), $response->body()));
        }

        return $response->json() ?? [];
    }

    public function verifyWebhook(string $signature, string $timestamp, string $rawBody): bool
    {
        $secret = (string) config('payin.webhook_secret');
        if ($secret === '' || $signature === '' || $timestamp === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);

        return hash_equals($expected, $signature);
    }

    protected function http(): PendingRequest
    {
        if (! $this->enabled()) {
            throw new RuntimeException('PayIn is not configured.');
        }

        return Http::timeout(30)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'X-API-Key' => (string) config('payin.api_key'),
                'X-API-Secret' => (string) config('payin.api_secret'),
            ]);
    }

    protected function url(string $path): string
    {
        return rtrim((string) config('payin.base_url'), '/').'/'.ltrim($path, '/');
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    protected function errorMessage(?array $json, string $body): string
    {
        if (is_array($json)) {
            if (! empty($json['message']) && is_string($json['message'])) {
                return $json['message'];
            }
            if (! empty($json['errors']) && is_array($json['errors'])) {
                $first = collect($json['errors'])->flatten()->first();
                if (is_string($first)) {
                    return $first;
                }
            }
        }

        return trim($body) !== '' ? Str::limit(trim($body), 200) : 'PayIn request failed.';
    }
}
