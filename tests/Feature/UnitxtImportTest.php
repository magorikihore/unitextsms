<?php

namespace Tests\Feature;

use App\Enums\SenderIdStatus;
use App\Models\Client;
use App\Models\UnitxtImportMap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UnitxtImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_persist(): void
    {
        $this->artisan('unitxt:import-clients', [
            '--file' => 'tests/Fixtures/unitxt-export-sample.json',
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame(0, Client::query()->count());
        $this->assertSame(0, UnitxtImportMap::query()->count());
    }

    public function test_execute_imports_client_balance_sender_ids_and_contacts(): void
    {
        $this->artisan('unitxt:import-clients', [
            '--file' => 'tests/Fixtures/unitxt-export-sample.json',
            '--with-contacts' => true,
            '--execute' => true,
        ])
            ->expectsConfirmation('Import clients into the LIVE database?', 'yes')
            ->assertSuccessful();

        $client = Client::query()->where('contact_email', 'pilot.client@example.test')->firstOrFail();
        $this->assertSame('Pilot Co Ltd', $client->company_name);
        $this->assertEquals(125.5, (float) $client->sms_balance);

        $user = User::query()->where('email', 'pilot.client@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertTrue($client->users()->where('users.id', $user->id)->exists());

        $this->assertTrue($client->senderIds()->where('sender', 'PILOT')->where('status', SenderIdStatus::Approved)->exists());
        $this->assertTrue($client->senderIds()->where('sender', 'OLDPILOT')->where('status', SenderIdStatus::Suspended)->exists());
        $this->assertSame(1, $client->contacts()->count());

        $this->artisan('unitxt:import-clients', [
            '--file' => 'tests/Fixtures/unitxt-export-sample.json',
            '--execute' => true,
        ])
            ->expectsConfirmation('Import clients into the LIVE database?', 'yes')
            ->assertSuccessful();

        $this->assertSame(1, UnitxtImportMap::query()->count());
        $this->assertSame(1, Client::query()->where('contact_email', 'pilot.client@example.test')->count());
    }
}
