<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Support\IpAllowList;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SystemSettingsService
{
    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'provider_tps' => (int) config('sms.provider_tps', 20),
            'otp_tps' => (int) config('sms.rate_limits.sms-otp', 10),
            'transactional_tps' => (int) config('sms.rate_limits.sms-transactional', 6),
            'bulk_tps' => (int) config('sms.rate_limits.sms-bulk', 4),
            'otp_ttl_minutes' => (int) config('sms.otp.ttl_minutes', 5),
            'otp_latency_pause_ms' => (int) config('sms.otp_latency_pause_ms', 3000),
            'otp_failure_pause_rate' => (float) config('sms.otp_failure_pause_rate', 0.25),
            'marketing_hours_start' => (string) config('sms.marketing_hours.start', '08:00'),
            'marketing_hours_end' => (string) config('sms.marketing_hours.end', '20:00'),
            'marketing_timezone' => (string) config('sms.marketing_hours.timezone', 'Africa/Dar_es_Salaam'),
            'bulk_paused' => (bool) Cache::get('sms:bulk:paused'),
            'dlr_allowed_ips' => implode("\n", IpAllowList::parse(config('sms.dlr_allowed_ips', []))),
            'mailgun_domain' => (string) config('services.mailgun.domain', ''),
            'mailgun_secret' => '',
            'mailgun_secret_set' => filled(config('services.mailgun.secret')),
            'mailgun_endpoint' => (string) config('services.mailgun.endpoint', 'api.mailgun.net'),
            'mail_from_address' => (string) config('mail.from.address', ''),
            'mail_from_name' => (string) config('mail.from.name', ''),
            'mail_mailer' => (string) config('mail.default', 'log'),
            'payin_enabled' => (bool) config('payin.enabled', false),
            'payin_base_url' => (string) config('payin.base_url', ''),
            'payin_api_key' => '',
            'payin_api_key_set' => filled(config('payin.api_key')),
            'payin_api_secret' => '',
            'payin_api_secret_set' => filled(config('payin.api_secret')),
            'payin_webhook_secret' => '',
            'payin_webhook_secret_set' => filled(config('payin.webhook_secret')),
            'payin_currency' => (string) config('payin.currency', 'TZS'),
            'payin_min_amount' => (int) config('payin.min_amount', 100),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function current(): array
    {
        $values = $this->defaults();

        if (! $this->tableReady()) {
            return $values;
        }

        foreach (array_keys($values) as $key) {
            if (in_array($key, ['bulk_paused', 'mailgun_secret', 'payin_api_key', 'payin_api_secret', 'payin_webhook_secret'], true)
                || str_ends_with($key, '_set')) {
                continue;
            }

            $stored = SystemSetting::getValue($key);
            if ($stored !== null) {
                $values[$key] = $stored;
            }
        }

        $values['bulk_paused'] = (bool) Cache::get('sms:bulk:paused');
        $values['mailgun_secret_set'] = filled(SystemSetting::getValue('mailgun_secret') ?: config('services.mailgun.secret'));
        $values['payin_api_key_set'] = filled(SystemSetting::getValue('payin_api_key') ?: config('payin.api_key'));
        $values['payin_api_secret_set'] = filled(SystemSetting::getValue('payin_api_secret') ?: config('payin.api_secret'));
        $values['payin_webhook_secret_set'] = filled(SystemSetting::getValue('payin_webhook_secret') ?: config('payin.webhook_secret'));

        return $values;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(array $data, ?SmsCapacityGuard $capacity = null): void
    {
        $otp = (int) $data['otp_tps'];
        $txn = (int) $data['transactional_tps'];
        $bulk = (int) $data['bulk_tps'];
        $provider = (int) $data['provider_tps'];

        if (($otp + $txn + $bulk) > $provider) {
            throw new \RuntimeException('OTP + transactional + bulk TPS cannot exceed provider TPS.');
        }

        foreach ([
            'provider_tps' => $provider,
            'otp_tps' => $otp,
            'transactional_tps' => $txn,
            'bulk_tps' => $bulk,
            'otp_ttl_minutes' => (int) $data['otp_ttl_minutes'],
            'otp_latency_pause_ms' => (int) $data['otp_latency_pause_ms'],
            'otp_failure_pause_rate' => (float) $data['otp_failure_pause_rate'],
            'marketing_hours_start' => (string) $data['marketing_hours_start'],
            'marketing_hours_end' => (string) $data['marketing_hours_end'],
            'marketing_timezone' => (string) $data['marketing_timezone'],
            'dlr_allowed_ips' => implode("\n", IpAllowList::parse($data['dlr_allowed_ips'] ?? '')),
            'mailgun_domain' => (string) ($data['mailgun_domain'] ?? ''),
            'mailgun_endpoint' => (string) ($data['mailgun_endpoint'] ?? 'api.mailgun.net'),
            'mail_from_address' => (string) ($data['mail_from_address'] ?? ''),
            'mail_from_name' => (string) ($data['mail_from_name'] ?? ''),
            'mail_mailer' => (string) ($data['mail_mailer'] ?? 'log'),
            'payin_enabled' => ! empty($data['payin_enabled']),
            'payin_base_url' => rtrim((string) ($data['payin_base_url'] ?? ''), '/'),
            'payin_currency' => (string) ($data['payin_currency'] ?? 'TZS'),
            'payin_min_amount' => (int) ($data['payin_min_amount'] ?? 100),
        ] as $key => $value) {
            SystemSetting::setValue($key, $value, $this->groupFor($key));
        }

        foreach ([
            'mailgun_secret' => 'mailgun_secret',
            'payin_api_key' => 'payin_api_key',
            'payin_api_secret' => 'payin_api_secret',
            'payin_webhook_secret' => 'payin_webhook_secret',
        ] as $input => $key) {
            $secret = trim((string) ($data[$input] ?? ''));
            if ($secret !== '') {
                SystemSetting::setValue($key, $secret, $this->groupFor($key));
            }
        }

        $this->applyToConfig();

        $capacity ??= app(SmsCapacityGuard::class);
        if (! empty($data['bulk_paused'])) {
            $capacity->pauseBulk('Manually paused from System Settings');
        } else {
            Cache::forget('sms:bulk:paused');
        }
    }

    public function applyToConfig(): void
    {
        if (! $this->tableReady()) {
            return;
        }

        $map = [
            'provider_tps' => 'sms.provider_tps',
            'otp_ttl_minutes' => 'sms.otp.ttl_minutes',
            'otp_latency_pause_ms' => 'sms.otp_latency_pause_ms',
            'otp_failure_pause_rate' => 'sms.otp_failure_pause_rate',
            'marketing_hours_start' => 'sms.marketing_hours.start',
            'marketing_hours_end' => 'sms.marketing_hours.end',
            'marketing_timezone' => 'sms.marketing_hours.timezone',
            'mailgun_domain' => 'services.mailgun.domain',
            'mailgun_secret' => 'services.mailgun.secret',
            'mailgun_endpoint' => 'services.mailgun.endpoint',
            'mail_from_address' => 'mail.from.address',
            'mail_from_name' => 'mail.from.name',
            'mail_mailer' => 'mail.default',
            'payin_enabled' => 'payin.enabled',
            'payin_base_url' => 'payin.base_url',
            'payin_api_key' => 'payin.api_key',
            'payin_api_secret' => 'payin.api_secret',
            'payin_webhook_secret' => 'payin.webhook_secret',
            'payin_currency' => 'payin.currency',
            'payin_min_amount' => 'payin.min_amount',
        ];

        foreach ($map as $key => $configKey) {
            $value = SystemSetting::getValue($key);
            if ($value !== null && $value !== '') {
                config([$configKey => $value]);
            }
        }

        $dlr = SystemSetting::getValue('dlr_allowed_ips');
        if ($dlr !== null) {
            config(['sms.dlr_allowed_ips' => IpAllowList::parse($dlr)]);
        }

        $otp = SystemSetting::getValue('otp_tps');
        $txn = SystemSetting::getValue('transactional_tps');
        $bulk = SystemSetting::getValue('bulk_tps');

        if ($otp !== null) {
            config(['sms.rate_limits.sms-otp' => (int) $otp]);
        }
        if ($txn !== null) {
            config(['sms.rate_limits.sms-transactional' => (int) $txn]);
        }
        if ($bulk !== null) {
            config(['sms.rate_limits.sms-bulk' => (int) $bulk]);
        }
    }

    protected function groupFor(string $key): string
    {
        return match (true) {
            str_starts_with($key, 'mail') || str_starts_with($key, 'mailgun') => 'mail',
            str_starts_with($key, 'payin') => 'payin',
            $key === 'dlr_allowed_ips' => 'security',
            default => 'sms',
        };
    }

    protected function tableReady(): bool
    {
        try {
            return Schema::hasTable('system_settings');
        } catch (\Throwable) {
            return false;
        }
    }
}
