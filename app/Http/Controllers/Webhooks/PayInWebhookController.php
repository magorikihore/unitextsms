<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\PayIn\DepositService;
use App\Services\PayIn\PayInClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PayInWebhookController extends Controller
{
    public function __invoke(Request $request, PayInClient $payIn, DepositService $deposits): Response
    {
        $raw = $request->getContent();
        $signature = (string) $request->header('X-Payin-Signature', '');
        $timestamp = (string) $request->header('X-Payin-Timestamp', '');

        if (! filled(config('payin.webhook_secret'))) {
            Log::warning('PayIn webhook rejected: webhook secret not configured');

            return response('Webhook not configured', 503);
        }

        if (! $payIn->verifyWebhook($signature, $timestamp, $raw)) {
            Log::warning('PayIn webhook signature rejected');

            return response('Invalid signature', 401);
        }

        $payload = $request->all();
        if (($payload['type'] ?? null) === 'collection' || str_starts_with((string) ($payload['event'] ?? ''), 'payin.')) {
            $deposits->applyWebhook($payload);
        }

        return response('OK', 200);
    }
}
