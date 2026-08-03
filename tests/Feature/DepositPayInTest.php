<?php

namespace Tests\Feature;

use App\Enums\ClientRole;
use App\Models\Client;
use App\Models\Deposit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DepositPayInTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_start_deposit_and_poll_until_completed(): void
    {
        config([
            'payin.enabled' => true,
            'payin.api_key' => 'pk_test',
            'payin.api_secret' => 'sk_test',
            'payin.base_url' => 'https://api.sandbox.payin.co.tz/api/v1',
            'payin.webhook_secret' => 'whsec',
        ]);

        [$user, $client] = $this->clientContext();

        Http::fake([
            'https://api.sandbox.payin.co.tz/api/v1/collection' => Http::response([
                'success' => true,
                'request_ref' => 'PAYTEST123',
                'status' => 'processing',
                'operator' => 'M-Pesa',
            ], 201),
            'https://api.sandbox.payin.co.tz/api/v1/status/PAYTEST123' => Http::sequence()
                ->push(['request_ref' => 'PAYTEST123', 'status' => 'waiting'], 200)
                ->push([
                    'request_ref' => 'PAYTEST123',
                    'status' => 'completed',
                    'gross_amount' => 5000,
                    'receipt_number' => 'MPI999',
                ], 200),
        ]);

        $start = $this->actingAs($user)->postJson(route('client.deposits.store', $client), [
            'phone' => '0714123456',
            'amount' => 5000,
        ]);

        $start->assertOk()
            ->assertJsonPath('deposit.request_ref', 'PAYTEST123')
            ->assertJsonPath('deposit.status', 'processing');

        $uuid = $start->json('deposit.uuid');

        $this->actingAs($user)
            ->getJson(route('client.deposits.status', [$client, $uuid]))
            ->assertOk()
            ->assertJsonPath('deposit.status', 'waiting');

        $done = $this->actingAs($user)
            ->getJson(route('client.deposits.status', [$client, $uuid]));

        $done->assertOk()
            ->assertJsonPath('deposit.is_completed', true)
            ->assertJsonPath('balance', 5100);

        $this->assertEquals(5100.0, (float) $client->fresh()->sms_balance);
        $this->assertSame('completed', Deposit::query()->first()->status);
        $this->assertSame('mobile', \App\Models\CreditLedgerEntry::query()->latest('id')->first()->payment_method->value);
    }

    public function test_payin_webhook_credits_once(): void
    {
        config([
            'payin.enabled' => true,
            'payin.api_key' => 'pk_test',
            'payin.api_secret' => 'sk_test',
            'payin.webhook_secret' => 'whsec',
        ]);

        [$user, $client] = $this->clientContext();

        $deposit = Deposit::query()->create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'phone' => '255714123456',
            'amount' => 2000,
            'currency' => 'TZS',
            'status' => 'processing',
            'request_ref' => 'PAYWEBHOOK1',
        ]);

        $body = json_encode([
            'event' => 'payin.completed',
            'request_ref' => 'PAYWEBHOOK1',
            'type' => 'collection',
            'gross_amount' => 2000,
            'status' => 'completed',
            'receipt_number' => 'MPI111',
        ], JSON_THROW_ON_ERROR);

        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsec');

        $this->call(
            'POST',
            route('webhooks.payin'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYIN_SIGNATURE' => $signature,
                'HTTP_X_PAYIN_TIMESTAMP' => $timestamp,
            ],
            $body,
        )->assertOk();

        $this->assertSame('completed', $deposit->fresh()->status);
        $this->assertEquals(2100.0, (float) $client->fresh()->sms_balance);

        $this->call(
            'POST',
            route('webhooks.payin'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYIN_SIGNATURE' => $signature,
                'HTTP_X_PAYIN_TIMESTAMP' => $timestamp,
            ],
            $body,
        )->assertOk();

        $this->assertEquals(2100.0, (float) $client->fresh()->sms_balance);
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function clientContext(): array
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['sms_balance' => 100, 'price_per_sms' => 0.05]);
        $client->users()->attach($user->id, ['role' => ClientRole::Owner->value, 'is_active' => true]);

        return [$user, $client];
    }
}
