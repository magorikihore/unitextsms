<?php

namespace Tests\Feature;

use App\Enums\MessageStatus;
use App\Models\Message;
use App\Models\SystemAlert;
use App\Models\User;
use App\Services\JasminGatewayHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JasminGatewayHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_opens_alert_when_jasmin_unreachable(): void
    {
        config(['sms.jasmin.base_url' => 'http://jasmin.test:1401']);

        Http::fake([
            'http://jasmin.test:1401/*' => Http::response('down', 500),
        ]);

        $snapshot = app(JasminGatewayHealth::class)->check();

        $this->assertFalse($snapshot['healthy']);
        $this->assertSame('down', $snapshot['status']);
        $this->assertTrue(
            SystemAlert::query()->where('type', 'gateway')->where('is_resolved', false)->exists()
        );
    }

    public function test_health_check_resolves_alert_when_jasmin_recovers(): void
    {
        config(['sms.jasmin.base_url' => 'http://jasmin.test:1401']);

        SystemAlert::query()->create([
            'type' => 'gateway',
            'severity' => 'critical',
            'title' => 'Jasmin gateway unreachable',
            'body' => 'probe failed',
            'is_resolved' => false,
        ]);

        Http::fake([
            'http://jasmin.test:1401/*' => Http::response('0.11.1', 200),
        ]);

        Message::factory()->create([
            'status' => MessageStatus::Delivered,
            'delivered_at' => now(),
            'submitted_at' => now()->subSecond(),
        ]);

        $snapshot = app(JasminGatewayHealth::class)->check();

        $this->assertTrue($snapshot['healthy']);
        $this->assertSame('ok', $snapshot['status']);
        $this->assertTrue(
            SystemAlert::query()->where('type', 'gateway')->where('is_resolved', true)->exists()
        );
    }

    public function test_admin_dashboard_includes_gateway_health(): void
    {
        config(['sms.jasmin.base_url' => 'http://jasmin.test:1401']);
        Http::fake(['http://jasmin.test:1401/*' => Http::response('ok', 200)]);

        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Jasmin gateway', false);
    }
}
