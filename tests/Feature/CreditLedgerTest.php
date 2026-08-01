<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\CreditLedgerEntry;
use App\Models\User;
use App\Services\CreditLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_and_debit_update_balance_via_ledger_only(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $client = Client::factory()->create(['sms_balance' => 0]);

        $ledger = app(CreditLedgerService::class);
        $ledger->credit($client, 100, 'Top up', $admin);
        $this->assertEquals(100.0, (float) $client->fresh()->sms_balance);

        $ledger->debit($client, 25, 'Campaign charge', $admin);
        $client->refresh();

        $this->assertEquals(75.0, (float) $client->sms_balance);
        $this->assertSame(2, CreditLedgerEntry::query()->count());
        $this->assertEquals(75.0, (float) CreditLedgerEntry::query()->latest('id')->first()->balance_after);
    }

    public function test_debit_fails_when_insufficient_prepaid_balance(): void
    {
        $this->expectException(\RuntimeException::class);

        $client = Client::factory()->create(['sms_balance' => 10, 'billing_type' => 'prepaid']);
        app(CreditLedgerService::class)->debit($client, 50, 'Too much');
    }
}
