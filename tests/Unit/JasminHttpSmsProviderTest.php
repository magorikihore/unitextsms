<?php

namespace Tests\Unit;

use App\Services\Sms\JasminHttpSmsProvider;
use Tests\TestCase;

class JasminHttpSmsProviderTest extends TestCase
{
    public function test_parses_quoted_success_message_id(): void
    {
        $provider = new JasminHttpSmsProvider;

        $result = $provider->parseSendResponse('Success "abc-123-msgid"');

        $this->assertTrue($result['success']);
        $this->assertSame('abc-123-msgid', $result['provider_message_id']);
    }

    public function test_parses_unquoted_success_message_id(): void
    {
        $provider = new JasminHttpSmsProvider;

        $result = $provider->parseSendResponse('Success abc-123-msgid');

        $this->assertTrue($result['success']);
        $this->assertSame('abc-123-msgid', $result['provider_message_id']);
    }

    public function test_rejects_success_prefixed_failure_traceback(): void
    {
        $provider = new JasminHttpSmsProvider;

        $body = 'Success "[Failure instance: Traceback: <class \'txamqp.client.ChannelClosed\'>: Method(name=close, id=40) (406, \'PRECONDITION_FAILED - delivery acknowledgement on channel 1 timed out. Timeout value used: 1800000 ms.\', 0, 0) content = None]"';

        $result = $provider->parseSendResponse($body);

        $this->assertFalse($result['success']);
        $this->assertArrayNotHasKey('provider_message_id', $result);
        $this->assertStringContainsString('PRECONDITION_FAILED', $result['error']);
    }

    public function test_rejects_oversized_message_id(): void
    {
        $provider = new JasminHttpSmsProvider;

        $result = $provider->parseSendResponse('Success "'.str_repeat('a', 200).'"');

        $this->assertFalse($result['success']);
        $this->assertSame('Jasmin returned an invalid message id', $result['error']);
    }

    public function test_rejects_non_success_body(): void
    {
        $provider = new JasminHttpSmsProvider;

        $result = $provider->parseSendResponse('Error "Invalid credentials"');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid credentials', $result['error']);
    }
}
