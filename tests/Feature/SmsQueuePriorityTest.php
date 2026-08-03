<?php

namespace Tests\Feature;

use App\Enums\ClientRole;
use App\Enums\MessageCategory;
use App\Enums\MessageStatus;
use App\Jobs\SendBulkSms;
use App\Jobs\SendOtpSms;
use App\Jobs\SendTransactionalSms;
use App\Models\Client;
use App\Models\Message;
use App\Models\SenderId;
use App\Models\User;
use App\Services\OtpService;
use App\Services\SmsCapacityGuard;
use App\Services\SmsDispatchService;
use App\Support\SmsJobFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SmsQueuePriorityTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_dispatches_to_correct_priority_jobs(): void
    {
        Queue::fake();

        $client = Client::factory()->create();
        $otp = Message::factory()->create([
            'client_id' => $client->id,
            'category' => MessageCategory::Otp,
            'queue_name' => 'sms-otp',
        ]);
        $txn = Message::factory()->create([
            'client_id' => $client->id,
            'category' => MessageCategory::Transactional,
            'queue_name' => 'sms-transactional',
        ]);
        $bulk = Message::factory()->create([
            'client_id' => $client->id,
            'category' => MessageCategory::Marketing,
            'queue_name' => 'sms-bulk',
        ]);

        SmsJobFactory::dispatch($otp);
        SmsJobFactory::dispatch($txn);
        SmsJobFactory::dispatch($bulk);

        Queue::assertPushedOn('sms-otp', SendOtpSms::class);
        Queue::assertPushedOn('sms-transactional', SendTransactionalSms::class);
        Queue::assertPushedOn('sms-bulk', SendBulkSms::class);
    }

    public function test_expired_otp_is_not_sent(): void
    {
        $client = Client::factory()->create(['sms_balance' => 100]);
        $message = Message::factory()->create([
            'client_id' => $client->id,
            'category' => MessageCategory::Otp,
            'status' => MessageStatus::Queued,
            'queue_name' => 'sms-otp',
            'expires_at' => now()->subMinute(),
        ]);

        (new SendOtpSms($message->id))->handle(
            app(\App\Services\Sms\SmsGateway::class),
            app(SmsCapacityGuard::class),
        );

        $this->assertSame(MessageStatus::Expired, $message->fresh()->status);
    }

    public function test_otp_request_invalidates_previous_pending_challenge(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['sms_balance' => 100, 'can_send_otp' => true]);
        $client->users()->attach($user->id, ['role' => ClientRole::Owner->value, 'is_active' => true]);
        $sender = SenderId::factory()->create(['client_id' => $client->id]);

        Queue::fake();

        $otp = app(OtpService::class);
        $first = $otp->request($client, $user, $sender, '0758460813', '111111');
        $second = $otp->request($client, $user, $sender, '0758460813', '222222');

        $this->assertSame('invalidated', $first['challenge']->fresh()->status);
        $this->assertSame('pending', $second['challenge']->fresh()->status);
        $this->assertSame(MessageStatus::Cancelled, $first['message']->fresh()->status);
    }

    public function test_bulk_pauses_when_otp_latency_is_high(): void
    {
        Cache::put('sms:otp:avg_latency_ms', 5000, 600);

        $guard = app(SmsCapacityGuard::class);
        $this->assertTrue($guard->shouldPauseBulk());
    }

    public function test_transactional_dispatch_uses_transactional_queue(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $client = Client::factory()->create(['sms_balance' => 100]);
        $client->users()->attach($user->id, ['role' => ClientRole::Owner->value, 'is_active' => true]);
        $sender = SenderId::factory()->create(['client_id' => $client->id]);

        app(SmsDispatchService::class)->sendSingle(
            $client,
            $user,
            $sender,
            '0758460813',
            'Payment received',
            MessageCategory::Transactional,
        );

        Queue::assertPushedOn('sms-transactional', SendTransactionalSms::class);
    }
}
