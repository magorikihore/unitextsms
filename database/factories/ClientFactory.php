<?php

namespace Database\Factories;

use App\Enums\ClientStatus;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'company_name' => fake()->company(),
            'contact_name' => fake()->name(),
            'contact_email' => fake()->unique()->companyEmail(),
            'contact_phone' => '075'.fake()->numerify('######'),
            'status' => ClientStatus::Active,
            'billing_type' => 'prepaid',
            'sms_balance' => 500,
            'credit_limit' => 0,
            'price_per_sms' => 0.05,
            'low_balance_threshold' => 50,
            'can_send_otp' => true,
            'can_send_transactional' => true,
            'can_send_marketing' => true,
            'api_enabled' => true,
            'allowed_countries' => ['TZ'],
            'approved_at' => now(),
        ];
    }
}
