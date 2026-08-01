<?php

namespace Database\Seeders;

use App\Enums\ClientRole;
use App\Enums\ClientStatus;
use App\Enums\MessageCategory;
use App\Enums\PlatformRole;
use App\Enums\SenderIdStatus;
use App\Enums\TemplateStatus;
use App\Models\Client;
use App\Models\SenderId;
use App\Models\SmsProvider;
use App\Models\SmsRoute;
use App\Models\Template;
use App\Models\User;
use App\Services\CreditLedgerService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@unintext.test',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'platform_role' => PlatformRole::Owner,
            'is_active' => true,
        ]);

        User::query()->create([
            'name' => 'Finance User',
            'email' => 'finance@unintext.test',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'platform_role' => PlatformRole::Finance,
            'is_active' => true,
        ]);

        $provider = SmsProvider::query()->create([
            'name' => 'Local Log Gateway',
            'driver' => 'log',
            'is_active' => true,
            'rate_limit_per_second' => 18,
            'balance' => 50000,
            'cost_per_sms' => 0.02,
            'credentials' => [],
            'health_status' => 'healthy',
        ]);

        foreach (['otp', 'transactional', 'marketing'] as $i => $category) {
            SmsRoute::query()->create([
                'sms_provider_id' => $provider->id,
                'name' => strtoupper($category).' TZ',
                'category' => $category,
                'country_code' => 'TZ',
                'priority' => $i + 1,
                'is_active' => true,
            ]);
        }

        $clientUser = User::query()->create([
            'name' => 'Demo Client Owner',
            'email' => 'client@unintext.test',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $client = Client::query()->create([
            'company_name' => 'Demo Logistics Ltd',
            'contact_name' => 'Demo Client Owner',
            'contact_email' => 'client@unintext.test',
            'contact_phone' => '0758460813',
            'city' => 'Dar es Salaam',
            'country' => 'TZ',
            'status' => ClientStatus::Active,
            'billing_type' => 'prepaid',
            'sms_balance' => 0,
            'price_per_sms' => 0.05,
            'low_balance_threshold' => 50,
            'can_send_otp' => true,
            'can_send_transactional' => true,
            'can_send_marketing' => true,
            'api_enabled' => true,
            'default_provider_id' => $provider->id,
            'allowed_countries' => ['TZ'],
            'approved_at' => now(),
            'approved_by' => $admin->id,
        ]);

        $client->users()->attach($clientUser->id, [
            'role' => ClientRole::Owner->value,
            'is_active' => true,
        ]);

        app(CreditLedgerService::class)->credit(
            $client,
            1000,
            'Initial demo credit package',
            $admin,
        );

        SenderId::query()->create([
            'client_id' => $client->id,
            'sender' => 'DEMOLOG',
            'status' => SenderIdStatus::Approved,
            'category' => null,
            'approved_at' => now(),
            'reviewed_by' => $admin->id,
        ]);

        Template::query()->create([
            'client_id' => $client->id,
            'name' => 'OTP EN',
            'category' => MessageCategory::Otp,
            'language' => 'en',
            'body' => 'Your verification code is {{code}}. Valid for 5 minutes.',
            'variables' => ['code'],
            'status' => TemplateStatus::Approved,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        Template::query()->create([
            'client_id' => $client->id,
            'name' => 'OTP SW',
            'category' => MessageCategory::Otp,
            'language' => 'sw',
            'body' => 'Habari {{first_name}}, namba yako ya uthibitisho ni {{code}}.',
            'variables' => ['first_name', 'code'],
            'status' => TemplateStatus::Approved,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);
    }
}
