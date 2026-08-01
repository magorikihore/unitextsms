<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\ClientRole;
use App\Models\Client;
use App\Models\SenderId;
use App\Models\User;
use App\Services\SmsDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_launch_creates_messages_and_debits_balance(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['sms_balance' => 50, 'price_per_sms' => 0.05]);
        $client->users()->attach($user->id, ['role' => ClientRole::Owner->value, 'is_active' => true]);
        $sender = SenderId::factory()->create(['client_id' => $client->id]);

        $campaign = app(SmsDispatchService::class)->createCampaign(
            $client,
            $user,
            [
                'name' => 'Promo',
                'sender_id_id' => $sender->id,
                'category' => 'marketing',
                'message_body' => 'Hello {{first_name}}',
            ],
            ['0758460813', '0758460813', '0712345678', 'bad-number'],
            launch: true,
        );

        $this->assertSame(CampaignStatus::Completed, $campaign->fresh()->status);
        $this->assertSame(2, $campaign->valid_count);
        $this->assertSame(1, $campaign->duplicate_count);
        $this->assertSame(1, $campaign->invalid_count);
        $this->assertEquals(49.90, (float) $client->fresh()->sms_balance);
    }
}
