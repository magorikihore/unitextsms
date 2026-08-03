<?php

namespace Tests\Feature;

use App\Enums\PlatformRole;
use App\Models\SystemAlert;
use App\Models\User;
use App\Services\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMissingPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_nav_pages_are_available(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        foreach ([
            'admin.campaigns.index',
            'admin.alerts.index',
            'admin.users.index',
            'admin.settings.index',
            'admin.account.edit',
        ] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
    }

    public function test_admin_can_create_and_resolve_alert(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->post(route('admin.alerts.store'), [
                'type' => 'provider',
                'severity' => 'warning',
                'title' => 'Gateway slow',
                'body' => 'OTP latency rising',
            ])
            ->assertRedirect();

        $alert = SystemAlert::query()->firstOrFail();
        $this->assertFalse($alert->is_resolved);

        $this->actingAs($admin)
            ->post(route('admin.alerts.resolve', $alert))
            ->assertRedirect();

        $this->assertTrue($alert->fresh()->is_resolved);
    }

    public function test_admin_can_create_platform_user(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Support Lead',
                'email' => 'support@unintext.test',
                'password' => 'password123',
                'platform_role' => PlatformRole::Support->value,
                'department' => 'Support',
                'is_active' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'support@unintext.test',
            'platform_role' => 'support',
            'department' => 'Support',
        ]);
    }

    public function test_admin_can_update_platform_user_role_and_department(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $user = User::factory()->platformAdmin()->create([
            'platform_role' => PlatformRole::Support,
            'department' => 'Support',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'platform_role' => PlatformRole::Finance->value,
                'department' => 'Operations',
                'is_active' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'platform_role' => 'finance',
            'department' => 'Operations',
        ]);
    }

    public function test_admin_can_reset_platform_user_password(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $user = User::factory()->platformAdmin()->create([
            'platform_role' => PlatformRole::Support,
            'password' => 'old-password',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.reset-password', $user), [
                'password' => 'new-password123',
                'password_confirmation' => 'new-password123',
            ])
            ->assertRedirect();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-password123', $user->fresh()->password));
    }

    public function test_admin_can_update_system_settings(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'provider_tps' => 20,
                'otp_tps' => 10,
                'transactional_tps' => 6,
                'bulk_tps' => 4,
                'otp_ttl_minutes' => 4,
                'otp_latency_pause_ms' => 2500,
                'otp_failure_pause_rate' => 0.2,
                'marketing_hours_start' => '09:00',
                'marketing_hours_end' => '18:00',
                'marketing_timezone' => 'Africa/Dar_es_Salaam',
                'bulk_paused' => 0,
                'dlr_allowed_ips' => "127.0.0.1\n41.220.143.37",
                'mail_mailer' => 'mailgun',
                'mailgun_domain' => 'mg.example.com',
                'mailgun_secret' => 'key-test',
                'mailgun_endpoint' => 'api.mailgun.net',
                'mail_from_address' => 'noreply@example.com',
                'mail_from_name' => 'UniNText',
                'payin_enabled' => 0,
                'payin_base_url' => 'https://api.sandbox.payin.co.tz/api/v1',
                'payin_currency' => 'TZS',
                'payin_min_amount' => 100,
            ])
            ->assertRedirect();

        $settings = app(SystemSettingsService::class)->current();
        $this->assertSame(4, (int) $settings['otp_ttl_minutes']);
        $this->assertSame('09:00', $settings['marketing_hours_start']);
        $this->assertSame('mailgun', $settings['mail_mailer']);
        $this->assertTrue($settings['mailgun_secret_set']);
        $this->assertStringContainsString('41.220.143.37', $settings['dlr_allowed_ips']);
    }
}
