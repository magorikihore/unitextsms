<?php

namespace Tests\Feature;

use App\Enums\ClientRole;
use App\Enums\ClientStatus;
use App\Enums\MessageStatus;
use App\Enums\SenderIdStatus;
use App\Jobs\DeliverClientDlrWebhook;
use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\Message;
use App\Models\SenderId;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ClientApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_send_and_status_with_key_secret(): void
    {
        [$client, $credential, $secret] = $this->makeApiClient();

        $response = $this->withHeaders([
            'X-Api-Key' => $credential->key_id,
            'X-Api-Secret' => $secret,
        ])->postJson('/api/v1/sms', [
            'to' => '0758460813',
            'from' => 'APITEST',
            'message' => 'Hello API',
            'category' => 'transactional',
        ]);

        $response->assertStatus(202)->assertJsonPath('ok', true);
        $uuid = $response->json('message.uuid');
        $this->assertNotEmpty($uuid);

        $this->withHeaders([
            'X-Api-Key' => $credential->key_id,
            'X-Api-Secret' => $secret,
        ])->getJson('/api/v1/sms/'.$uuid)
            ->assertOk()
            ->assertJsonPath('message.uuid', $uuid);

        $this->assertDatabaseHas('messages', [
            'uuid' => $uuid,
            'client_id' => $client->id,
            'api_credential_id' => $credential->id,
        ]);
    }

    public function test_dlr_dispatches_client_webhook(): void
    {
        Queue::fake();

        [$client, $credential] = $this->makeApiClient(webhook: 'https://example.test/hooks/sms');

        $message = Message::factory()->create([
            'client_id' => $client->id,
            'api_credential_id' => $credential->id,
            'status' => MessageStatus::Submitted,
            'provider_message_id' => 'JASMIN-123',
            'from_sender' => 'APITEST',
            'queue_name' => 'sms-transactional',
        ]);

        $this->post('/dlr/jasmin', [
            'id' => 'JASMIN-123',
            'message_status' => 'DELIVRD',
            'level' => 2,
        ])->assertOk()
            ->assertSee('ACK/Jasmin', false);

        Queue::assertPushed(DeliverClientDlrWebhook::class, function (DeliverClientDlrWebhook $job) use ($credential, $message) {
            return $job->credentialId === $credential->id
                && $job->payload['message_uuid'] === $message->uuid
                && $job->payload['status'] === 'delivered';
        });
    }

    public function test_submit_error_marks_invalid_sender_as_failed(): void
    {
        Queue::fake();

        [$client, $credential] = $this->makeApiClient();

        $message = Message::factory()->create([
            'client_id' => $client->id,
            'api_credential_id' => $credential->id,
            'status' => MessageStatus::Submitted,
            'provider_message_id' => 'JASMIN-BAD-SENDER',
            'from_sender' => 'DEMOLOG',
            'queue_name' => 'sms-transactional',
        ]);

        $this->post('/dlr/jasmin-submit-error', [
            'id' => 'JASMIN-BAD-SENDER',
            'message_status' => 'ESME_RINVSRCADR',
        ])->assertOk()
            ->assertSee('ACK/Jasmin', false);

        $message->refresh();
        $this->assertSame(MessageStatus::Failed, $message->status);
        $this->assertSame('Invalid sender ID (rejected by network)', $message->failure_reason);
    }

    public function test_webhook_job_posts_signed_payload(): void
    {
        Http::fake([
            'https://example.test/*' => Http::response(['ok' => true], 200),
        ]);

        [, $credential] = $this->makeApiClient(webhook: 'https://example.test/hooks/sms');

        $job = new DeliverClientDlrWebhook($credential->id, [
            'event' => 'sms.dlr',
            'message_uuid' => 'abc',
            'status' => 'delivered',
        ]);
        $job->handle();

        Http::assertSent(function ($request) use ($credential) {
            $raw = $request->body();
            $expected = 'sha256='.hash_hmac('sha256', $raw, $credential->webhook_signing_secret);

            return $request->url() === 'https://example.test/hooks/sms'
                && $request->header('X-UniNText-Signature')[0] === $expected;
        });
    }

    /**
     * @return array{0: Client, 1: ApiCredential, 2: string}
     */
    protected function makeApiClient(?string $webhook = null): array
    {
        $user = User::factory()->create();
        $client = Client::factory()->create([
            'status' => ClientStatus::Active,
            'sms_balance' => 100,
            'api_enabled' => true,
            'can_send_transactional' => true,
        ]);
        $client->users()->attach($user->id, ['role' => ClientRole::Owner->value, 'is_active' => true]);

        SenderId::factory()->create([
            'client_id' => $client->id,
            'sender' => 'APITEST',
            'status' => SenderIdStatus::Approved,
        ]);

        $secret = 'us_testsecretvaluefortests1234567890';
        $credential = ApiCredential::query()->create([
            'client_id' => $client->id,
            'name' => 'Test',
            'key_id' => 'uk_testkey123456789012345',
            'secret_hash' => Hash::make($secret),
            'secret_hint' => '7890',
            'webhook_url' => $webhook,
            'webhook_signing_secret' => 'whsec_testsigningsecret',
            'is_active' => true,
            'rate_limit_per_minute' => 120,
        ]);

        return [$client, $credential, $secret];
    }
}
