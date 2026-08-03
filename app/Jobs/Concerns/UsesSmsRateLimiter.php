<?php

namespace App\Jobs\Concerns;

use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;

trait UsesSmsRateLimiter
{
    protected function smsRateLimitMiddleware(string $limiter): object
    {
        $useRedis = config('queue.default') === 'redis'
            || config('cache.default') === 'redis'
            || env('SMS_FORCE_REDIS_LIMITER', false);

        $middleware = $useRedis
            ? new RateLimitedWithRedis($limiter)
            : new RateLimited($limiter);

        return $middleware->releaseAfter(1);
    }
}
