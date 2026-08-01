<?php

namespace Tests\Feature;

use App\Enums\ClientRole;
use App\Enums\MessageCategory;
use App\Enums\MessageStatus;
use App\Models\Client;
use App\Models\Message;
use App\Models\SenderId;
use App\Models\User;
use App\Services\SmsDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmsDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_sms_charges_ledger_and_queues_message(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['sms_balance' => 100, 'price_per_sms' => 0.05]);
        $client->users()->attach($user->id, ['role' => ClientRole::Owner->value, 'is_active' => true]);
        $sender = SenderId::factory()->create(['client_id' => $client->id, 'sender' => 'TESTSID']);

        $message = app(SmsDispatchService::class)->sendSingle(
            $client,
            $user,
            $sender,
            '0758460813',
            'Hello world',
            MessageCategory::Transactional,
        );

        $this->assertSame(MessageStatus::Delivered, $message->fresh()->status);
        $this->assertSame('sms-transactional', $message->queue_name);
        $this->assertEquals(99.95, (float) $client->fresh()->sms_balance);
        $this->assertSame(1, Message::query()->count());
    }

    public function test_rejects_unapproved_sender_id(): void
    {
        $this->expectException(\RuntimeException::class);

        $user = User::factory()->create();
        $client = Client::factory()->create(['sms_balance' => 100]);
        $sender = SenderId::factory()->create([
            'client_id' => $client->id,
            'status' => 'pending',
        ]);

        app(SmsDispatchService::class)->sendSingle(
            $client,
            $user,
            $sender,
            '0758460813',
            'Hello',
            MessageCategory::Transactional,
        );
    }
}
