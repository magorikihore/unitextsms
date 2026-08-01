<?php

namespace App\Http\Controllers\Api;

use App\Enums\MessageStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryReportController extends Controller
{
    public function jasmin(Request $request): JsonResponse
    {
        $providerId = $request->input('id') ?? $request->input('message_id');
        $status = strtoupper((string) $request->input('message_status', $request->input('level', '')));

        if (! $providerId) {
            return response()->json(['ok' => false, 'error' => 'missing id'], 422);
        }

        $message = Message::query()->where('provider_message_id', $providerId)->first();
        if (! $message) {
            return response()->json(['ok' => true, 'matched' => false]);
        }

        $mapped = match (true) {
            in_array($status, ['DELIVRD', 'DELIVERED', '1', '2'], true) => MessageStatus::Delivered,
            in_array($status, ['EXPIRED', 'DELETED'], true) => MessageStatus::Expired,
            in_array($status, ['REJECTD', 'REJECTED'], true) => MessageStatus::Rejected,
            in_array($status, ['UNDELIV', 'FAILED', 'UNKNOWN'], true) => MessageStatus::Failed,
            default => MessageStatus::Submitted,
        };

        $updates = ['status' => $mapped];

        if ($mapped === MessageStatus::Delivered) {
            $updates['delivered_at'] = now();
            if ($message->submitted_at) {
                $updates['delivery_ms'] = $message->submitted_at->diffInMilliseconds(now());
            }
        }

        if (in_array($mapped, [MessageStatus::Failed, MessageStatus::Rejected, MessageStatus::Expired], true)) {
            $updates['failed_at'] = now();
            $updates['failure_reason'] = $status;
        }

        $message->update($updates);

        if ($message->campaign_id) {
            $campaign = Campaign::query()->whereKey($message->campaign_id)->lockForUpdate()->first();
            if ($campaign) {
                if ($mapped === MessageStatus::Delivered) {
                    $campaign->increment('delivered_count');
                }
                if (in_array($mapped, [MessageStatus::Failed, MessageStatus::Rejected, MessageStatus::Expired], true)) {
                    $campaign->increment('failed_count');
                }
            }
        }

        return response()->json(['ok' => true, 'matched' => true, 'status' => $mapped->value]);
    }
}
