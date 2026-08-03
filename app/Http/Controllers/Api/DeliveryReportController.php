<?php

namespace App\Http\Controllers\Api;

use App\Enums\MessageStatus;
use App\Http\Controllers\Controller;
use App\Jobs\DeliverClientDlrWebhook;
use App\Models\Campaign;
use App\Models\Message;
use App\Support\SmsStatusPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeliveryReportController extends Controller
{
    public function jasmin(Request $request): Response
    {
        $providerId = $request->input('id') ?? $request->input('message_id');
        // Jasmin "level" is DLR type (1=SMSC, 2=terminal) — never treat it as delivery status.
        $status = strtoupper((string) (
            $request->input('message_status')
            ?? $request->input('status')
            ?? ''
        ));
        $level = $request->input('level');

        if (! $providerId) {
            return $this->ack();
        }

        Log::info('Jasmin DLR received', [
            'id' => $providerId,
            'message_status' => $status,
            'level' => $level,
            'payload' => $request->except(['password']),
        ]);

        $message = Message::query()->where('provider_message_id', $providerId)->first();
        if (! $message) {
            return $this->ack();
        }

        $mapped = $this->mapStatus($status);
        if ($mapped === null) {
            Log::warning('Jasmin DLR unmapped status', [
                'id' => $providerId,
                'message_status' => $status,
                'level' => $level,
                'message_uuid' => $message->uuid,
            ]);

            return $this->ack();
        }

        // Never downgrade a terminal delivery once confirmed.
        if (
            $message->status === MessageStatus::Delivered
            && $mapped !== MessageStatus::Delivered
        ) {
            return $this->ack();
        }

        $humanReason = SmsStatusPresenter::humanize($status);
        $updates = [
            'status' => $mapped,
            'meta' => array_merge($message->meta ?? [], [
                'last_dlr_status' => $status,
                'last_dlr_level' => $level,
                'last_dlr_at' => now()->toIso8601String(),
            ]),
        ];

        if ($mapped === MessageStatus::Delivered) {
            $updates['delivered_at'] = $message->delivered_at ?? now();
            if ($message->submitted_at) {
                $updates['delivery_ms'] = $message->submitted_at->diffInMilliseconds(now());
            }
        }

        if (in_array($mapped, [MessageStatus::Failed, MessageStatus::Rejected, MessageStatus::Expired], true)) {
            $updates['failed_at'] = now();
            $updates['failure_reason'] = $humanReason ?: $status;
        }

        DB::transaction(function () use ($message, $updates, $mapped) {
            $wasDelivered = $message->status === MessageStatus::Delivered;
            $wasFailed = in_array($message->status, [
                MessageStatus::Failed,
                MessageStatus::Rejected,
                MessageStatus::Expired,
            ], true);

            $message->update($updates);

            if ($message->campaign_id) {
                $campaign = Campaign::query()->whereKey($message->campaign_id)->lockForUpdate()->first();
                if ($campaign) {
                    if ($mapped === MessageStatus::Delivered && ! $wasDelivered) {
                        $campaign->increment('delivered_count');
                    }
                    if (
                        in_array($mapped, [MessageStatus::Failed, MessageStatus::Rejected, MessageStatus::Expired], true)
                        && ! $wasFailed
                    ) {
                        $campaign->increment('failed_count');
                    }
                }
            }
        });

        DeliverClientDlrWebhook::dispatchForMessage($message->fresh(), $status);

        return $this->ack();
    }

    /**
     * Called by the Jasmin submit-error watcher when SMSC rejects before a normal DLR exists
     * (e.g. ESME_RINVSRCADR for a bad sender ID).
     */
    public function jasminSubmitError(Request $request): Response
    {
        $providerId = $request->input('id') ?? $request->input('message_id');
        $status = strtoupper((string) ($request->input('message_status') ?? $request->input('status') ?? 'ESME_RSUBMITFAIL'));

        if (! $providerId) {
            return $this->ack();
        }

        // Reuse the same mapping path as DLRs.
        $request->merge([
            'id' => $providerId,
            'message_status' => $status,
            'level' => $request->input('level', 'submit-error'),
        ]);

        return $this->jasmin($request);
    }

    protected function mapStatus(string $status): ?MessageStatus
    {
        return match (true) {
            in_array($status, ['DELIVRD', 'DELIVERED'], true) => MessageStatus::Delivered,
            in_array($status, ['ACCEPTD', 'ACKED', 'ESME_ROK'], true) => MessageStatus::Submitted,
            in_array($status, ['EXPIRED', 'DELETED'], true) => MessageStatus::Expired,
            in_array($status, ['REJECTD', 'REJECTED'], true) => MessageStatus::Rejected,
            in_array($status, ['UNDELIV', 'FAILED', 'UNKNOWN'], true) => MessageStatus::Failed,
            str_starts_with($status, 'ESME_R'),
            str_contains($status, 'ERROR/COMMANDSTATUS'),
            str_contains($status, 'RINVSRCADR'),
            str_contains($status, 'RINVDSTADR') => MessageStatus::Failed,
            default => null,
        };
    }

    protected function ack(): Response
    {
        // Jasmin requires this exact plain-text ACK body.
        return response('ACK/Jasmin', 200)->header('Content-Type', 'text/plain');
    }
}
