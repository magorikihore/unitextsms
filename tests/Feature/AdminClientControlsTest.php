<?php

namespace Tests\Feature;

use App\Enums\ClientRole;
use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\CreditLedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminClientControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_and_remove_credit_on_client(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $client = Client::factory()->create(['sms_balance' => 100, 'status' => ClientStatus::Active]);

        $this->actingAs($admin)
            ->post(route('admin.clients.credits', $client), [
                'action' => 'credit',
                'amount' => 50,
                'reason' => 'Manual top-up',
                'payment_method' => 'bank',
            ])
            ->assertRedirect();

        $this->assertEquals(150, (float) $client->fresh()->sms_balance);
        $this->assertSame(1, CreditLedgerEntry::query()->where('client_id', $client->id)->count());
        $this->assertSame('bank', CreditLedgerEntry::query()->first()->payment_method->value);

        $this->actingAs($admin)
            ->post(route('admin.clients.credits', $client), [
                'action' => 'credit',
                'amount' => 10,
                'reason' => 'Missing method',
            ])
            ->assertSessionHasErrors('payment_method');

        $this->actingAs($admin)
            ->post(route('admin.clients.credits', $client), [
                'action' => 'debit',
                'amount' => 25,
                'reason' => 'Manual adjustment',
            ])
            ->assertRedirect();

        $this->assertEquals(125, (float) $client->fresh()->sms_balance);
        $this->assertSame(2, CreditLedgerEntry::query()->where('client_id', $client->id)->count());
    }

    public function test_admin_can_deactivate_activate_and_reset_password(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $owner = User::factory()->create(['password' => Hash::make('old-password')]);
        $client = Client::factory()->create(['status' => ClientStatus::Active]);
        $client->users()->attach($owner->id, ['role' => ClientRole::Owner->value, 'is_active' => true]);

        $this->actingAs($admin)
            ->post(route('admin.clients.suspend', $client))
            ->assertRedirect();
        $this->assertSame(ClientStatus::Suspended, $client->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.clients.activate', $client))
            ->assertRedirect();
        $this->assertSame(ClientStatus::Active, $client->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.clients.users.reset-password', [$client, $owner]), [
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertRedirect();

        $this->assertTrue(Hash::check('NewPassword123!', $owner->fresh()->password));
    }
}
