<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_profile_and_password(): void
    {
        $admin = User::factory()->platformAdmin()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'department' => 'Ops',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.account.edit'))
            ->assertOk()
            ->assertSee('Your profile')
            ->assertSee('Change password');

        $this->actingAs($admin)
            ->put(route('admin.account.profile'), [
                'name' => 'Platform Admin',
                'email' => 'platform@example.com',
                'department' => 'Support',
            ])
            ->assertRedirect(route('admin.account.edit'));

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'name' => 'Platform Admin',
            'email' => 'platform@example.com',
            'department' => 'Support',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.account.password'), [
                'current_password' => 'password',
                'password' => 'admin-secret-99',
                'password_confirmation' => 'admin-secret-99',
            ])
            ->assertRedirect(route('admin.account.edit'));

        $admin->refresh();
        $this->assertTrue(Hash::check('admin-secret-99', $admin->password));
    }
}
