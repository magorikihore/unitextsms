<?php

namespace App\Support;

class SmsStatusPresenter
{
    public static function humanize(?string $codeOrReason): ?string
    {
        if ($codeOrReason === null || trim($codeOrReason) === '') {
            return null;
        }

        $raw = trim($codeOrReason);
        $key = strtoupper($raw);

        return match (true) {
            str_contains($key, 'ESME_RINVSRCADR'),
            str_contains($key, 'RINVSRCADR'),
            str_contains($key, 'INVALID SOURCE') => 'Invalid sender ID (rejected by network)',
            str_contains($key, 'ESME_RINVDSTADR'),
            str_contains($key, 'RINVDSTADR') => 'Invalid destination number (rejected by network)',
            str_contains($key, 'ESME_RINVMSGLEN') => 'Invalid message length (rejected by network)',
            str_contains($key, 'ESME_RSUBMITFAIL') => 'Submit failed at SMSC',
            str_contains($key, 'ESME_RTHROTTLED') => 'Throttled by SMSC — retry shortly',
            str_contains($key, 'REJECTD'),
            str_contains($key, 'REJECTED') => 'Rejected by network',
            str_contains($key, 'UNDELIV') => 'Undelivered by network',
            str_contains($key, 'EXPIRED') => 'Expired before delivery',
            str_contains($key, 'PRECONDITION_FAILED'),
            str_contains($key, 'CHANNELCLOSED'),
            str_contains($key, 'CHANNEL CLOSED'),
            str_contains($key, 'JASMIN GATEWAY') => 'Gateway temporarily unavailable — please retry',
            default => $raw,
        };
    }

    public static function isInvalidSender(?string $codeOrReason): bool
    {
        $key = strtoupper((string) $codeOrReason);

        return str_contains($key, 'ESME_RINVSRCADR')
            || str_contains($key, 'RINVSRCADR')
            || str_contains($key, 'INVALID SOURCE')
            || str_contains($key, 'INVALID SENDER');
    }
}
