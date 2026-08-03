<?php

namespace Tests\Unit;

use App\Services\SmsCounter;
use PHPUnit\Framework\TestCase;

class SmsCounterTest extends TestCase
{
    public function test_short_gsm_is_one_segment(): void
    {
        $counter = new SmsCounter;
        $body = str_repeat('A', 160);

        $this->assertSame(1, $counter->units($body));
        $this->assertCount(1, $counter->segments($body));
    }

    public function test_long_gsm_splits_into_independent_sms_parts(): void
    {
        $counter = new SmsCounter;
        $body = str_repeat('A', 273);

        $segments = $counter->segments($body);

        $this->assertSame(2, $counter->units($body));
        $this->assertCount(2, $segments);
        $this->assertSame(160, mb_strlen($segments[0]));
        $this->assertSame(113, mb_strlen($segments[1]));
        $this->assertSame($body, implode('', $segments));
    }

    public function test_unicode_uses_70_char_chunks(): void
    {
        $counter = new SmsCounter;
        $body = str_repeat('字', 71);

        $segments = $counter->segments($body);

        $this->assertTrue($counter->isUnicode($body));
        $this->assertSame(2, $counter->units($body));
        $this->assertCount(2, $segments);
        $this->assertSame(70, mb_strlen($segments[0]));
        $this->assertSame(1, mb_strlen($segments[1]));
    }
}
