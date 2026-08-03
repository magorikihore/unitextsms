<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SenderIdStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\SenderId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        /** @var Client $client */
        $client = $request->attributes->get('api_client');

        return response()->json([
            'ok' => true,
            'client' => [
                'uuid' => $client->uuid,
                'company_name' => $client->company_name,
                'balance' => (float) $client->sms_balance,
                'price_per_sms' => (float) $client->price_per_sms,
                'currency' => 'TZS',
                'api_enabled' => (bool) $client->api_enabled,
            ],
            'sender_ids' => SenderId::query()
                ->where('client_id', $client->id)
                ->where('status', SenderIdStatus::Approved)
                ->orderBy('sender')
                ->get(['sender', 'status', 'expires_at'])
                ->map(fn (SenderId $s) => [
                    'sender' => $s->sender,
                    'status' => $s->status->value,
                    'expires_at' => $s->expires_at?->toDateString(),
                ]),
        ]);
    }
}
