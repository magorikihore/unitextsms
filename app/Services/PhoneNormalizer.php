<?php

namespace App\Services;

class PhoneNormalizer
{
    /**
     * Normalise Tanzanian MSISDNs to E.164 without plus: 255XXXXXXXXX
     */
    public function normalize(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '255') && strlen($digits) === 12) {
            return $this->isValidTzMobile($digits) ? $digits : null;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $normalized = '255'.substr($digits, 1);

            return $this->isValidTzMobile($normalized) ? $normalized : null;
        }

        if (strlen($digits) === 9 && in_array($digits[0], ['6', '7'], true)) {
            $normalized = '255'.$digits;

            return $this->isValidTzMobile($normalized) ? $normalized : null;
        }

        return null;
    }

    public function isValid(string $raw): bool
    {
        return $this->normalize($raw) !== null;
    }

    protected function isValidTzMobile(string $normalized): bool
    {
        // Tanzania mobile prefixes commonly 6x / 7x after country code
        return (bool) preg_match('/^255[67]\d{8}$/', $normalized);
    }
}
