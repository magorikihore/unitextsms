<?php

namespace App\Services;

class SmsCounter
{
    public function units(string $body): int
    {
        $length = mb_strlen($body, 'UTF-8');
        $isUnicode = $this->isUnicode($body);

        if ($isUnicode) {
            if ($length <= 70) {
                return 1;
            }

            return (int) ceil($length / 67);
        }

        if ($length <= 160) {
            return 1;
        }

        return (int) ceil($length / 153);
    }

    public function isUnicode(string $body): bool
    {
        return preg_match('/[^\x00-\x7F]/', $body) === 1;
    }
}
