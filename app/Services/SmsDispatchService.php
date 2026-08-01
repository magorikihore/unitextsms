<?php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Enums\ClientStatus;
use App\Enums\MessageCategory;
use App\Enums\MessageStatus;
use App\Enums\SenderIdStatus;
use App\Jobs\SendSmsMessage;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Message;
use App\Models\SenderId;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SmsDispatchService
{
    public function __construct(
        private readonly RecipientPreviewService $previewService,
        private readonly CreditLedgerService $ledger,
        private readonly SmsCounter $smsCounter,
        private readonly MessagePersonalizer $personalizer,
        private readonly AuditLogger $auditLogger,
        private readonly PhoneNormalizer $phoneNormalizer,
    ) {}

    /**
     * @param  array<int, string>  $numbers
     * @param  array<string, mixed>  $options
     */
    public function sendSingle(
        Client $client,
        User $user,
        SenderId $senderId,
        string $to,
        string $body,
        MessageCategory $category = MessageCategory::Transactional,
        array $vars = [],
    ): Message {
        $this->assertClientCanSend($client, $category);
        $this->assertSender($client, $senderId, $category);

        $normalized = $this->phoneNormalizer->normalize($to);
        if (! $normalized) {
            throw new RuntimeException('Invalid phone number.');
        }

        $rendered = $this->personalizer->render($body, $vars);
        $units = $this->smsCounter->units($rendered);
        $charge = round($units * (float) $client->price_per_sms, 4);

        return DB::transaction(function () use ($client, $user, $senderId, $to, $normalized, $rendered, $category, $units, $charge) {
            $message = Message::query()->create([
                'client_id' => $client->id,
                'sender_id_id' => $senderId->id,
                'created_by' => $user->id,
                'to_phone' => $to,
                'to_phone_normalized' => $normalized,
                'from_sender' => $senderId->sender,
                'body' => $rendered,
                'category' => $category,
                'status' => MessageStatus::Queued,
                'queue_name' => $category->queue(),
                'units' => $units,
                'client_charge' => $charge,
                'provider_cost' => $client->defaultProvider?->cost_per_sms ?? 0,
                'sms_provider_id' => $client->default_provider_id,
                'queued_at' => now(),
            ]);

            $this->ledger->debit($client, (float) $charge, 'SMS send '.$message->uuid, $user, $message);
            SendSmsMessage::dispatch($message->id)->onQueue($category->queue());

            $this->auditLogger->log('sms.single.queued', $message, null, $message->toArray(), $user, $client->id);

            return $message;
        });
    }

    /**
     * @param  array<int, string>  $numbers
     */
    public function createCampaign(
        Client $client,
        User $user,
        array $data,
        array $numbers,
        bool $launch = false,
        bool $schedule = false,
    ): Campaign {
        $category = MessageCategory::from($data['category'] ?? MessageCategory::Marketing->value);
        $this->assertClientCanSend($client, $category);

        /** @var SenderId $senderId */
        $senderId = SenderId::query()->where('client_id', $client->id)->findOrFail($data['sender_id_id']);
        $this->assertSender($client, $senderId, $category);

        $body = $data['message_body'];
        $preview = $this->previewService->preview($client, $numbers, $body);
        $contentHash = hash('sha256', $client->id.'|'.$body.'|'.implode(',', $preview['valid']));

        if ($launch) {
            $duplicate = Campaign::query()
                ->where('client_id', $client->id)
                ->where('content_hash', $contentHash)
                ->where('created_at', '>=', now()->subMinutes(10))
                ->whereIn('status', [CampaignStatus::Processing, CampaignStatus::Scheduled, CampaignStatus::Completed])
                ->exists();

            if ($duplicate) {
                throw new RuntimeException('Duplicate campaign protection: a similar campaign was launched recently.');
            }
        }

        $status = CampaignStatus::Draft;
        if ($schedule) {
            $status = CampaignStatus::Scheduled;
        } elseif ($launch) {
            $status = CampaignStatus::Processing;
        }

        return DB::transaction(function () use ($client, $user, $data, $senderId, $category, $body, $preview, $contentHash, $status, $launch) {
            $campaign = Campaign::query()->create([
                'client_id' => $client->id,
                'created_by' => $user->id,
                'sender_id_id' => $senderId->id,
                'template_id' => $data['template_id'] ?? null,
                'name' => $data['name'],
                'status' => $status,
                'category' => $category,
                'message_body' => $body,
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'total_recipients' => count($preview['valid']) + count($preview['invalid']) + count($preview['opted_out']) + $preview['duplicates_removed'],
                'valid_count' => count($preview['valid']),
                'invalid_count' => count($preview['invalid']),
                'duplicate_count' => $preview['duplicates_removed'],
                'opted_out_count' => count($preview['opted_out']),
                'estimated_units' => $preview['estimated_units'],
                'estimated_cost' => $preview['estimated_cost'],
                'content_hash' => $contentHash,
                'source_meta' => [
                    'valid_numbers' => $preview['valid'],
                    'invalid' => array_slice($preview['invalid'], 0, 50),
                    'opted_out' => array_slice($preview['opted_out'], 0, 50),
                ],
            ]);

            if ($launch) {
                $this->launchCampaign($campaign, $preview['valid'], $user);
            }

            $this->auditLogger->log('campaign.created', $campaign, null, $campaign->toArray(), $user, $client->id);

            return $campaign->fresh();
        });
    }

    /**
     * @param  array<int, string>  $validNumbers
     */
    public function launchCampaign(Campaign $campaign, array $validNumbers, ?User $user = null): void
    {
        $client = $campaign->client()->lockForUpdate()->firstOrFail();
        $category = $campaign->category;
        $senderId = $campaign->senderId;

        if (! $senderId || ! $senderId->isUsable()) {
            throw new RuntimeException('Sender ID is not approved for sending.');
        }

        if ($campaign->estimated_cost > $client->availableBalance()) {
            throw new RuntimeException('Insufficient balance to launch campaign.');
        }

        $units = $this->smsCounter->units($campaign->message_body);
        $chargePer = round($units * (float) $client->price_per_sms, 4);
        $totalCharge = round(count($validNumbers) * $chargePer, 2);

        $this->ledger->debit($client, $totalCharge, 'Campaign launch #'.$campaign->id, $user, $campaign);

        $campaign->update([
            'status' => CampaignStatus::Processing,
            'started_at' => now(),
            'charged_amount' => $totalCharge,
            'queued_count' => count($validNumbers),
        ]);

        $contacts = Contact::query()
            ->where('client_id', $client->id)
            ->whereIn('phone_normalized', $validNumbers)
            ->get()
            ->keyBy('phone_normalized');

        foreach ($validNumbers as $number) {
            $contact = $contacts->get($number);
            $vars = [
                'first_name' => $contact?->first_name ?? '',
                'last_name' => $contact?->last_name ?? '',
                'phone' => $number,
            ];
            if (is_array($contact?->custom_fields)) {
                $vars = array_merge($vars, $contact->custom_fields);
            }

            $body = $this->personalizer->render($campaign->message_body, $vars);

            $message = Message::query()->create([
                'client_id' => $client->id,
                'campaign_id' => $campaign->id,
                'sender_id_id' => $senderId->id,
                'contact_id' => $contact?->id,
                'created_by' => $user?->id,
                'to_phone' => $number,
                'to_phone_normalized' => $number,
                'from_sender' => $senderId->sender,
                'body' => $body,
                'category' => $category,
                'status' => MessageStatus::Queued,
                'queue_name' => $category->queue(),
                'units' => $units,
                'client_charge' => $chargePer,
                'provider_cost' => $client->defaultProvider?->cost_per_sms ?? 0,
                'sms_provider_id' => $client->default_provider_id,
                'queued_at' => now(),
            ]);

            SendSmsMessage::dispatch($message->id)->onQueue($category->queue());
        }
    }

    public function pauseCampaign(Campaign $campaign, User $user): void
    {
        if (! in_array($campaign->status, [CampaignStatus::Processing, CampaignStatus::Scheduled], true)) {
            throw new RuntimeException('Only processing or scheduled campaigns can be paused.');
        }

        $campaign->update(['status' => CampaignStatus::Paused]);

        Message::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', MessageStatus::Queued)
            ->update(['status' => MessageStatus::Cancelled, 'failure_reason' => 'Campaign paused']);

        $this->auditLogger->log('campaign.paused', $campaign, null, ['status' => 'paused'], $user, $campaign->client_id);
    }

    public function cancelCampaign(Campaign $campaign, User $user): void
    {
        if (in_array($campaign->status, [CampaignStatus::Completed, CampaignStatus::Cancelled], true)) {
            throw new RuntimeException('Campaign cannot be cancelled.');
        }

        $campaign->update(['status' => CampaignStatus::Cancelled, 'completed_at' => now()]);

        Message::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', MessageStatus::Queued)
            ->update(['status' => MessageStatus::Cancelled, 'failure_reason' => 'Campaign cancelled']);

        $this->auditLogger->log('campaign.cancelled', $campaign, null, ['status' => 'cancelled'], $user, $campaign->client_id);
    }

    public function duplicateCampaign(Campaign $campaign, User $user): Campaign
    {
        $copy = $campaign->replicate([
            'status', 'started_at', 'completed_at', 'queued_count', 'sent_count',
            'delivered_count', 'failed_count', 'charged_amount', 'failure_reason',
        ]);
        $copy->name = $campaign->name.' (copy)';
        $copy->status = CampaignStatus::Draft;
        $copy->created_by = $user->id;
        $copy->content_hash = null;
        $copy->save();

        $this->auditLogger->log('campaign.duplicated', $copy, null, ['from' => $campaign->id], $user, $campaign->client_id);

        return $copy;
    }

    protected function assertClientCanSend(Client $client, MessageCategory $category): void
    {
        if ($client->status !== ClientStatus::Active) {
            throw new RuntimeException('Client account is not active.');
        }

        $allowed = match ($category) {
            MessageCategory::Otp => $client->can_send_otp,
            MessageCategory::Transactional, MessageCategory::Alert => $client->can_send_transactional,
            MessageCategory::Marketing => $client->can_send_marketing,
        };

        if (! $allowed) {
            throw new RuntimeException('Client is not permitted to send '.$category->value.' messages.');
        }
    }

    protected function assertSender(Client $client, SenderId $senderId, MessageCategory $category): void
    {
        if ($senderId->client_id !== $client->id) {
            throw new RuntimeException('Sender ID does not belong to this client.');
        }

        if ($senderId->status !== SenderIdStatus::Approved || ! $senderId->isUsable()) {
            throw new RuntimeException('Sender ID is not approved.');
        }

        if ($senderId->category && $senderId->category !== $category) {
            throw new RuntimeException('Sender ID is restricted to '.$senderId->category->value.' messages.');
        }
    }
}
