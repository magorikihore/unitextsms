<?php

namespace Database\Factories;

use App\Enums\MessageCategory;
use App\Enums\MessageStatus;
use App\Models\Client;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'client_id' => Client::factory(),
            'to_phone' => '0758460813',
            'to_phone_normalized' => '255758460813',
            'from_sender' => 'TESTSID',
            'body' => 'Test message',
            'category' => MessageCategory::Transactional,
            'status' => MessageStatus::Queued,
            'queue_name' => 'sms-transactional',
            'units' => 1,
            'client_charge' => 0.05,
            'provider_cost' => 0.02,
            'queued_at' => now(),
        ];
    }
}
