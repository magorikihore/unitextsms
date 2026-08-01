<?php

namespace Database\Factories;

use App\Enums\SenderIdStatus;
use App\Models\Client;
use App\Models\SenderId;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SenderId>
 */
class SenderIdFactory extends Factory
{
    protected $model = SenderId::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'sender' => strtoupper(fake()->lexify('??????')),
            'status' => SenderIdStatus::Approved,
            'approved_at' => now(),
        ];
    }
}
