<?php

namespace Tests\Feature;

use App\Enums\MessageStatus;
use App\Jobs\SendTransactionalSms;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StuckQueuedMessageFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_handler_marks_queued_message_failed_with_truncated_reason(): void
    {
        $message = Message::factory()->create([
            'status' => MessageStatus::Queued,
            'queue_name' => 'sms-transactional',
            'failure_reason' => null,
            'failed_at' => null,
        ]);

        $job = new SendTransactionalSms($message->id);
        $job->failed(new \RuntimeException('provider_message_id overflow '.str_repeat('X', 500)));

        $message->refresh();

        $this->assertSame(MessageStatus::Failed, $message->status);
        $this->assertNotNull($message->failed_at);
        $this->assertLessThanOrEqual(240, mb_strlen((string) $message->failure_reason));
        $this->assertStringStartsWith('provider_message_id overflow', (string) $message->failure_reason);
    }

    public function test_failed_handler_does_not_overwrite_non_queued_status(): void
    {
        $message = Message::factory()->create([
            'status' => MessageStatus::Delivered,
            'queue_name' => 'sms-transactional',
            'failure_reason' => null,
        ]);

        $job = new SendTransactionalSms($message->id);
        $job->failed(new \RuntimeException('late failure'));

        $message->refresh();

        $this->assertSame(MessageStatus::Delivered, $message->status);
        $this->assertNull($message->failure_reason);
    }
}
