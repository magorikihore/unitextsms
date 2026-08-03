<?php

namespace App\Providers;

use App\Services\SystemSettingsService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useTailwind();
        app(SystemSettingsService::class)->applyToConfig();
        $this->configureSmsRateLimiters();
    }

    protected function configureSmsRateLimiters(): void
    {
        $limits = config('sms.rate_limits', [
            'sms-otp' => 10,
            'sms-transactional' => 6,
            'sms-bulk' => 4,
        ]);

        $providerTps = (int) config('sms.provider_tps', 20);
        $sum = array_sum($limits);
        if ($sum > $providerTps) {
            // Safety: scale down proportionally so we never exceed provider TPS.
            $scale = $providerTps / max($sum, 1);
            foreach ($limits as $key => $value) {
                $limits[$key] = max(1, (int) floor($value * $scale));
            }
        }

        RateLimiter::for('sms-otp', fn () => Limit::perSecond((int) $limits['sms-otp'])->by('sms-otp'));
        RateLimiter::for('sms-transactional', fn () => Limit::perSecond((int) $limits['sms-transactional'])->by('sms-transactional'));
        RateLimiter::for('sms-bulk', fn () => Limit::perSecond((int) $limits['sms-bulk'])->by('sms-bulk'));
    }
}
