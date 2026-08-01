<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Services\RecipientPreviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipientPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_removes_duplicates_invalid_and_opted_out(): void
    {
        $client = Client::factory()->create(['sms_balance' => 100, 'price_per_sms' => 0.05]);
        Contact::query()->create([
            'client_id' => $client->id,
            'phone' => '0711111111',
            'phone_normalized' => '255711111111',
            'is_opted_out' => true,
            'is_valid' => true,
        ]);

        $preview = app(RecipientPreviewService::class)->preview(
            $client,
            ['0758460813', '0758460813', '0711111111', 'abc'],
            'Hi there',
        );

        $this->assertSame(['255758460813'], $preview['valid']);
        $this->assertSame(1, $preview['duplicates_removed']);
        $this->assertSame(['255711111111'], $preview['opted_out']);
        $this->assertSame(['abc'], $preview['invalid']);
        $this->assertSame(1, $preview['estimated_units']);
        $this->assertEquals(0.05, $preview['estimated_cost']);
    }
}
