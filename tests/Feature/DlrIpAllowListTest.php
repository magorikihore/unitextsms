<?php

namespace Tests\Feature;

use App\Enums\MessageStatus;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DlrIpAllowListTest extends TestCase
{
    use RefreshDatabase;

    public function test_dlr_is_blocked_when_ip_not_allow_listed(): void
    {
        config(['sms.dlr_allowed_ips' => ['203.0.113.10']]);

        Message::factory()->create([
            'status' => MessageStatus::Submitted,
            'provider_message_id' => 'JASMIN-BLOCK',
        ]);

        $this->post('/dlr/jasmin', [
            'id' => 'JASMIN-BLOCK',
            'message_status' => 'DELIVRD',
            'level' => 2,
        ])->assertForbidden();
    }

    public function test_dlr_is_allowed_from_listed_ip(): void
    {
        config(['sms.dlr_allowed_ips' => ['127.0.0.1']]);

        $message = Message::factory()->create([
            'status' => MessageStatus::Submitted,
            'provider_message_id' => 'JASMIN-OK',
        ]);

        $this->post('/dlr/jasmin', [
            'id' => 'JASMIN-OK',
            'message_status' => 'DELIVRD',
            'level' => 2,
        ])->assertOk();

        $this->assertSame(MessageStatus::Delivered, $message->fresh()->status);
    }
}
