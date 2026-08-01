<?php

namespace Tests\Feature;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_create_and_approve_client(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->post(route('admin.clients.store'), [
                'company_name' => 'Acme TZ',
                'contact_name' => 'Asha',
                'contact_email' => 'asha@acme.test',
                'billing_type' => 'prepaid',
                'price_per_sms' => 0.05,
                'can_send_otp' => 1,
                'can_send_transactional' => 1,
                'owner_email' => 'owner@acme.test',
                'owner_name' => 'Owner',
                'owner_password' => 'password123',
            ])
            ->assertRedirect();

        $client = Client::query()->where('company_name', 'Acme TZ')->firstOrFail();
        $this->assertSame(ClientStatus::Pending, $client->status);

        $this->actingAs($admin)
            ->post(route('admin.clients.approve', $client))
            ->assertRedirect();

        $this->assertSame(ClientStatus::Active, $client->fresh()->status);
    }

    public function test_non_platform_user_cannot_access_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }
}
