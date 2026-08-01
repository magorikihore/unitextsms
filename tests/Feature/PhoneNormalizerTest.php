<?php

namespace Tests\Feature;

use App\Services\PhoneNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhoneNormalizerTest extends TestCase
{
    #[DataProvider('numbers')]
    public function test_normalizes_tanzanian_numbers(string $input, ?string $expected): void
    {
        $this->assertSame($expected, app(PhoneNormalizer::class)->normalize($input));
    }

    public static function numbers(): array
    {
        return [
            ['0758460813', '255758460813'],
            ['255758460813', '255758460813'],
            ['758460813', '255758460813'],
            ['+255 758 460 813', '255758460813'],
            ['12345', null],
            ['255158460813', null],
        ];
    }
}
