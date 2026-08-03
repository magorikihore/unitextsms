<?php

namespace App\Support;

class IpAllowList
{
    /**
     * @param  array<int, string>|string|null  $list
     */
    public static function parse(array|string|null $list): array
    {
        if ($list === null || $list === '') {
            return [];
        }

        if (is_string($list)) {
            $list = preg_split('/[\s,]+/', $list) ?: [];
        }

        return array_values(array_filter(array_map(
            static fn ($ip) => trim((string) $ip),
            $list,
        )));
    }

    /**
     * @param  array<int, string>  $allowed
     */
    public static function allows(string $ip, array $allowed): bool
    {
        if ($allowed === []) {
            return false;
        }

        foreach ($allowed as $entry) {
            if ($entry === $ip) {
                return true;
            }

            if (str_contains($entry, '/') && self::inCidr($ip, $entry)) {
                return true;
            }
        }

        return false;
    }

    public static function inCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = array_pad(explode('/', $cidr, 2), 2, null);
        if ($subnet === null || $mask === null || ! filter_var($subnet, FILTER_VALIDATE_IP) || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        $mask = (int) $mask;
        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $len = strlen($ipBin);
        $bits = $len * 8;
        if ($mask < 0 || $mask > $bits) {
            return false;
        }

        $bytes = intdiv($mask, 8);
        $remainder = $mask % 8;

        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $maskByte = (~((1 << (8 - $remainder)) - 1)) & 0xFF;

        return (ord($ipBin[$bytes]) & $maskByte) === (ord($subnetBin[$bytes]) & $maskByte);
    }
}
