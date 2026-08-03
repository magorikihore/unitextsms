<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\Client;
use App\Models\User;
use App\Services\CreditLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPaymentsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_customer_payments_by_receipt_and_method(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $client = Client::factory()->create(['company_name' => 'Acme TZ Ltd', 'sms_balance' => 0]);

        app(CreditLedgerService::class)->credit(
            $client,
            2500,
            'Office top-up · receipt RCPT-7788',
            $admin,
            null,
            ['payment_method' => 'bank', 'source' => 'admin_manual', 'receipt_number' => 'RCPT-7788'],
            PaymentMethod::Bank,
        );

        app(CreditLedgerService::class)->credit(
            $client,
            1000,
            'Cash desk',
            $admin,
            null,
            ['payment_method' => 'cash', 'source' => 'admin_manual', 'receipt_number' => 'CASH-1'],
            PaymentMethod::Cash,
        );

        $this->actingAs($admin)
            ->get(route('admin.credits.index', ['q' => 'RCPT-7788', 'payment_method' => 'bank']))
            ->assertOk()
            ->assertSee('Customer payments')
            ->assertSee('Acme TZ Ltd')
            ->assertSee('RCPT-7788')
            ->assertSee('Bank transfer')
            ->assertDontSee('Cash desk');

        $this->actingAs($admin)
            ->get(route('admin.credits.index', ['q' => 'Acme', 'payment_method' => 'cash']))
            ->assertOk()
            ->assertSee('Cash desk')
            ->assertDontSee('Office top-up');
    }
}
