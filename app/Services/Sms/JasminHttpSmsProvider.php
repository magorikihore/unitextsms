<?php

namespace App\Services\Sms;

use App\Models\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JasminHttpSmsProvider implements SmsProviderContract
{
    public function send(Message $message): array
    {
        $provider = $message->provider;
        $credentials = $provider?->credentials ?? [];

        $baseUrl = $credentials['base_url'] ?? config('sms.jasmin.base_url');
        $username = $credentials['username'] ?? config('sms.jasmin.username');
        $password = $credentials['password'] ?? config('sms.jasmin.password');

        if (! $baseUrl || ! $username || ! $password) {
            return ['success' => false, 'error' => 'Jasmin credentials are not configured.'];
        }

        try {
            $response = Http::timeout(15)->asForm()->get(rtrim($baseUrl, '/').'/send', [
                'username' => $username,
                'password' => $password,
                'to' => $message->to_phone_normalized,
                'from' => $message->from_sender,
                'content' => $message->body,
                'dlr' => 'yes',
                'dlr-url' => $provider?->webhook_url ?? config('sms.jasmin.dlr_url'),
                'dlr-level' => 2,
            ]);

            if ($response->successful() && str_contains($response->body(), 'Success')) {
                preg_match('/Success\s+"?(.*?)"?\s*$/i', trim($response->body()), $matches);

                return [
                    'success' => true,
                    'provider_message_id' => $matches[1] ?? trim($response->body()),
                ];
            }

            return [
                'success' => false,
                'error' => trim($response->body()) ?: 'Jasmin send failed',
            ];
        } catch (\Throwable $e) {
            Log::error('Jasmin send exception', ['error' => $e->getMessage(), 'message' => $message->uuid]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
