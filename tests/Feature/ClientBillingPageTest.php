<?php

namespace Tests\Feature;

use App\Enums\ClientRole;
use App\Enums\PaymentMethod;
use App\Models\Client;
use App\Models\Deposit;
use App\Models\User;
use App\Services\CreditLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientBillingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_view_and_filter_payments(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['sms_balance' => 0]);
        $client->users()->attach($user->id, ['role' => ClientRole::Owner->value, 'is_active' => true]);

        app(CreditLedgerService::class)->credit(
            $client,
            1000,
            'Bank top-up · receipt RCPT-99',
            $user,
            null,
            ['source' => 'admin_manual', 'receipt_number' => 'RCPT-99'],
            PaymentMethod::Bank,
        );

        app(CreditLedgerService::class)->credit(
            $client,
            500,
            'Cash desk',
            $user,
            null,
            ['source' => 'admin_manual', 'receipt_number' => 'CASH-2'],
            PaymentMethod::Cash,
        );

        Deposit::query()->create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'phone' => '255714123456',
            'amount' => 5000,
            'currency' => 'TZS',
            'credits' => 5000,
            'status' => 'completed',
            'request_ref' => 'PAYTEST',
            'completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('client.billing.index', $client))
            ->assertOk()
            ->assertSee('Payments & credits')
            ->assertSee('Credits bought')
            ->assertSee('Bank transfer')
            ->assertSee('Recent mobile payments')
            ->assertSee('255714123456');

        $this->actingAs($user)
            ->get(route('client.billing.index', [$client, 'q' => 'RCPT-99', 'payment_method' => 'bank']))
            ->assertOk()
            ->assertSee('Bank top-up')
            ->assertSee('RCPT-99')
            ->assertDontSee('Cash desk');

        $this->actingAs($user)
            ->get(route('client.billing.index', [$client, 'payment_method' => 'cash']))
            ->assertOk()
            ->assertSee('Cash desk')
            ->assertDontSee('Bank top-up');
    }
}
