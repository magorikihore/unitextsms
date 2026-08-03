<?php

namespace Tests\Feature;

use App\Enums\ClientRole;
use App\Models\Client;
use App\Models\SenderId;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClientSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_view_settings_and_update_profile_password_and_defaults(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'password' => Hash::make('password'),
        ]);
        $client = Client::factory()->create([
            'low_balance_threshold' => 100,
            'contact_name' => 'Desk',
            'contact_email' => 'desk@example.com',
        ]);
        $client->users()->attach($user->id, ['role' => ClientRole::Owner->value, 'is_active' => true]);
        $sender = SenderId::factory()->create(['client_id' => $client->id, 'sender' => 'BRAND']);

        $this->actingAs($user)
            ->get(route('client.settings.index', $client))
            ->assertOk()
            ->assertSee('Your profile')
            ->assertSee('Default sender ID')
            ->assertSee('Organisation details');

        $this->actingAs($user)
            ->put(route('client.settings.profile', $client), [
                'name' => 'New Name',
                'email' => 'new@example.com',
            ])
            ->assertRedirect(route('client.settings.index', $client));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);

        $this->actingAs($user)
            ->put(route('client.settings.password', $client), [
                'current_password' => 'password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect(route('client.settings.index', $client));

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-123', $user->password));

        $this->actingAs($user)
            ->put(route('client.settings.defaults', $client), [
                'default_sender_id_id' => $sender->id,
                'low_balance_threshold' => 250,
            ])
            ->assertRedirect(route('client.settings.index', $client));

        $client->refresh();
        $this->assertSame($sender->id, $client->default_sender_id_id);
        $this->assertEquals(250, (float) $client->low_balance_threshold);

        $this->actingAs($user)
            ->put(route('client.settings.company', $client), [
                'trading_name' => 'Trade Co',
                'contact_name' => 'Amina',
                'contact_email' => 'amina@example.com',
                'contact_phone' => '0714000000',
                'address' => 'Kijitonyama',
                'city' => 'Dar es Salaam',
            ])
            ->assertRedirect(route('client.settings.index', $client));

        $client->refresh();
        $this->assertSame('Trade Co', $client->trading_name);
        $this->assertSame('Amina', $client->contact_name);

        $sendPage = $this->actingAs($user)
            ->get(route('client.send.create', $client))
            ->assertOk();

        $this->assertStringContainsString('defaultSenderId:', $sendPage->getContent());
        $this->assertStringContainsString((string) $sender->id, $sendPage->getContent());
    }

    public function test_report_viewer_cannot_change_org_settings(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $client->users()->attach($user->id, ['role' => ClientRole::ReportViewer->value, 'is_active' => true]);

        $this->actingAs($user)
            ->get(route('client.settings.index', $client))
            ->assertOk()
            ->assertSee('Your profile')
            ->assertDontSee('Organisation details');

        $this->actingAs($user)
            ->put(route('client.settings.defaults', $client), [
                'default_sender_id_id' => null,
                'low_balance_threshold' => 10,
            ])
            ->assertForbidden();
    }
}
