<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MessageCategory;
use App\Enums\SenderIdStatus;
use App\Http\Controllers\Controller;
use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\Message;
use App\Models\SenderId;
use App\Services\SmsDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class SmsController extends Controller
{
    public function __construct(private readonly SmsDispatchService $dispatch) {}

    public function send(Request $request): JsonResponse
    {
        /** @var Client $client */
        $client = $request->attributes->get('api_client');
        /** @var ApiCredential $credential */
        $credential = $request->attributes->get('api_credential');

        $data = $request->validate([
            'to' => ['required', 'string', 'max:30'],
            'message' => ['required', 'string', 'max:1000'],
            'from' => ['nullable', 'string', 'max:20'],
            'sender' => ['nullable', 'string', 'max:20'],
            'category' => ['nullable', 'in:otp,transactional,alert,marketing,bulksms,bulk,trxn'],
        ]);

        $senderName = $data['from'] ?? $data['sender'] ?? null;
        $senderQuery = SenderId::query()
            ->where('client_id', $client->id)
            ->where('status', SenderIdStatus::Approved);

        $sender = $senderName
            ? $senderQuery->where('sender', $senderName)->first()
            : $senderQuery->orderBy('id')->first();

        if (! $sender) {
            return response()->json([
                'ok' => false,
                'error' => 'sender_not_found',
                'message' => 'Approved sender ID not found. Pass "from" or approve a sender ID first.',
            ], 422);
        }

        $category = MessageCategory::normalize($data['category'] ?? MessageCategory::Transactional->value);

        $actor = $client->users()
            ->wherePivot('role', 'owner')
            ->first()
            ?? $client->users()->first();

        try {
            $message = $this->dispatch->sendSingle(
                client: $client,
                user: $actor,
                senderId: $sender,
                to: $data['to'],
                body: $data['message'],
                category: $category,
                apiCredential: $credential,
            );
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'send_failed',
                'message' => $e->getMessage(),
            ], 422);
        }

        $segmentTotal = (int) ($message->meta['segment_total'] ?? 1);
        $totalCharge = round($segmentTotal * (float) $client->price_per_sms, 4);

        return response()->json([
            'ok' => true,
            'message' => [
                'uuid' => $message->uuid,
                'to' => $message->to_phone_normalized,
                'from' => $message->from_sender,
                'status' => $message->status->value,
                'category' => $message->category->value,
                'units' => $segmentTotal,
                'charge' => $totalCharge,
                'segments' => $segmentTotal,
                'queued_at' => $message->queued_at?->toIso8601String(),
            ],
        ], 202);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        /** @var Client $client */
        $client = $request->attributes->get('api_client');

        $message = Message::query()
            ->where('client_id', $client->id)
            ->where('uuid', $uuid)
            ->first();

        if (! $message) {
            return response()->json([
                'ok' => false,
                'error' => 'not_found',
                'message' => 'Message not found.',
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'message' => [
                'uuid' => $message->uuid,
                'to' => $message->to_phone_normalized,
                'from' => $message->from_sender,
                'body' => $message->body,
                'status' => $message->status->value,
                'category' => $message->category->value,
                'units' => $message->units,
                'charge' => (float) $message->client_charge,
                'failure_reason' => $message->failure_reason,
                'queued_at' => $message->queued_at?->toIso8601String(),
                'submitted_at' => $message->submitted_at?->toIso8601String(),
                'delivered_at' => $message->delivered_at?->toIso8601String(),
                'failed_at' => $message->failed_at?->toIso8601String(),
            ],
        ]);
    }
}
