<?php

namespace App\Services;

class SmsCounter
{
    /**
     * Billable SMS units for a body.
     *
     * Uses full single-SMS capacities (160 GSM-7 / 70 Unicode) because UniNText
     * sends long text as separate SMS submits, not one concatenated multipart PDU.
     */
    public function units(string $body): int
    {
        return max(1, count($this->segments($body)));
    }

    /**
     * Split a body into independent SMS payloads (one /send each).
     *
     * @return array<int, string>
     */
    public function segments(string $body): array
    {
        $length = mb_strlen($body, 'UTF-8');
        if ($length === 0) {
            return [''];
        }

        $chunkSize = $this->isUnicode($body) ? 70 : 160;
        if ($length <= $chunkSize) {
            return [$body];
        }

        $segments = [];
        for ($offset = 0; $offset < $length; $offset += $chunkSize) {
            $segments[] = mb_substr($body, $offset, $chunkSize, 'UTF-8');
        }

        return $segments;
    }

    public function isUnicode(string $body): bool
    {
        return preg_match('/[^\x00-\x7F]/', $body) === 1;
    }
}
