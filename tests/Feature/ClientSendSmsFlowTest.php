<?php

namespace Tests\Feature;

use App\Enums\ClientRole;
use App\Models\Client;
use App\Models\SenderId;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientSendSmsFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_returns_json_summary_for_ajax(): void
    {
        [$user, $client, $sender] = $this->clientContext();

        $response = $this->actingAs($user)
            ->postJson(route('client.send.preview', $client), [
                'name' => 'Test blast',
                'sender_id_id' => $sender->id,
                'send_mode' => 'bulk',
                'category' => 'marketing',
                'message_body' => 'Hello world',
                'numbers' => "0758460813\nbad-number",
            ]);

        $response->assertOk()
            ->assertJsonPath('preview.valid_count', 1)
            ->assertJsonPath('preview.invalid_count', 1)
            ->assertJsonPath('can_launch', true);
    }

    public function test_store_does_not_require_credit_checkbox_and_returns_summary(): void
    {
        [$user, $client, $sender] = $this->clientContext();

        $response = $this->actingAs($user)
            ->postJson(route('client.send.store', $client), [
                'name' => 'Launch me',
                'sender_id_id' => $sender->id,
                'send_mode' => 'bulk',
                'category' => 'marketing',
                'message_body' => 'Hello world',
                'numbers' => "0758460813\n0712345678",
                'action' => 'launch',
            ]);

        $response->assertOk()
            ->assertJsonPath('summary.title', 'Bulk SMS launched successfully')
            ->assertJsonPath('summary.valid_count', 2)
            ->assertJsonMissingPath('summary.confirm');

        $this->assertDatabaseHas('campaigns', [
            'client_id' => $client->id,
            'name' => 'Launch me',
            'category' => 'marketing',
        ]);
    }

    public function test_single_mode_sends_one_message_from_saved_contact(): void
    {
        [$user, $client, $sender] = $this->clientContext();

        $contact = \App\Models\Contact::query()->create([
            'client_id' => $client->id,
            'phone' => '0758460813',
            'phone_normalized' => '255758460813',
            'first_name' => 'Asha',
            'is_opted_out' => false,
            'is_valid' => true,
            'consent_status' => 'unknown',
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('client.send.store', $client), [
                'name' => 'OTP ping',
                'sender_id_id' => $sender->id,
                'send_mode' => 'single',
                'category' => 'otp',
                'message_body' => 'Your code is 1234',
                'contact_ids' => [$contact->id],
                'action' => 'single',
            ]);

        $response->assertOk()
            ->assertJsonPath('summary.title', 'SMS queued successfully')
            ->assertJsonPath('summary.valid_count', 1);

        $this->assertDatabaseHas('messages', [
            'client_id' => $client->id,
            'to_phone_normalized' => '255758460813',
            'category' => 'otp',
        ]);
    }

    public function test_single_mode_rejects_multiple_numbers(): void
    {
        [$user, $client, $sender] = $this->clientContext();

        $this->actingAs($user)
            ->postJson(route('client.send.preview', $client), [
                'name' => 'Too many',
                'sender_id_id' => $sender->id,
                'send_mode' => 'single',
                'category' => 'transactional',
                'message_body' => 'Hello',
                'numbers' => "0758460813\n0712345678",
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['numbers']);
    }

    public function test_compose_validation_is_inline_json_not_redirect(): void
    {
        [$user, $client] = $this->clientContext();

        $response = $this->actingAs($user)
            ->postJson(route('client.send.preview', $client), [
                'name' => '',
                'category' => 'transactional',
                'message_body' => '',
                'numbers' => '',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'sender_id_id', 'message_body']);
    }

    /**
     * @return array{0: User, 1: Client, 2: SenderId}
     */
    private function clientContext(): array
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['sms_balance' => 100, 'price_per_sms' => 0.05]);
        $client->users()->attach($user->id, ['role' => ClientRole::Owner->value, 'is_active' => true]);
        $sender = SenderId::factory()->create(['client_id' => $client->id]);

        return [$user, $client, $sender];
    }
}
