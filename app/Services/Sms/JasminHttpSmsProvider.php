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
                // Level 3 = SMSC + handset receipts. Level 2 alone often leaves
                // messages stuck in "submitted" when the network never returns terminal DLR.
                'dlr-level' => 3,
                'dlr-method' => 'POST',
            ]);

            $body = trim($response->body());
            $parsed = $this->parseSendResponse($body, $response->successful());

            if (! ($parsed['success'] ?? false)) {
                Log::warning('Jasmin send rejected', [
                    'message' => $message->uuid,
                    'http_status' => $response->status(),
                    'body' => mb_substr($body, 0, 500),
                    'error' => $parsed['error'] ?? null,
                ]);
            }

            return $parsed;
        } catch (\Throwable $e) {
            Log::error('Jasmin send exception', ['error' => $e->getMessage(), 'message' => $message->uuid]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{success: bool, provider_message_id?: string|null, error?: string|null}
     */
    public function parseSendResponse(string $body, bool $httpOk = true): array
    {
        $body = trim($body);

        if ($body === '') {
            return ['success' => false, 'error' => 'Jasmin send failed (empty response)'];
        }

        // Jasmin sometimes prefixes a Failure traceback with the word Success.
        // Never treat Failure / Traceback / ChannelClosed as a real submit.
        if (preg_match('/\b(Failure instance|Traceback|ChannelClosed|PRECONDITION_FAILED)\b/i', $body)) {
            return [
                'success' => false,
                'error' => $this->summarizeGatewayError($body),
            ];
        }

        // Real success looks like: Success "abc-123-msgid"
        if ($httpOk && preg_match('/^Success\s+"([^"]+)"\s*$/i', $body, $matches)) {
            $providerId = trim($matches[1]);

            if ($providerId === '' || strlen($providerId) > 191) {
                return ['success' => false, 'error' => 'Jasmin returned an invalid message id'];
            }

            return [
                'success' => true,
                'provider_message_id' => $providerId,
            ];
        }

        if ($httpOk && preg_match('/^Success\s+(\S+)\s*$/i', $body, $matches)) {
            $providerId = trim($matches[1], "\"'");

            if ($providerId === '' || strlen($providerId) > 191) {
                return ['success' => false, 'error' => 'Jasmin returned an invalid message id'];
            }

            return [
                'success' => true,
                'provider_message_id' => $providerId,
            ];
        }

        return [
            'success' => false,
            'error' => mb_substr($body, 0, 500) ?: 'Jasmin send failed',
        ];
    }

    protected function summarizeGatewayError(string $body): string
    {
        if (preg_match('/PRECONDITION_FAILED[^\'"]*/i', $body, $matches)) {
            return 'Jasmin gateway queue error: '.trim($matches[0]);
        }

        if (preg_match('/ChannelClosed[^\'"\]]*/i', $body, $matches)) {
            return 'Jasmin gateway channel closed: '.trim($matches[0]);
        }

        if (preg_match('/Failure instance:[^\]]+/i', $body, $matches)) {
            return 'Jasmin gateway failure: '.trim(mb_substr($matches[0], 0, 200));
        }

        return 'Jasmin gateway rejected the submit';
    }
}
