<?php

namespace App\Http\Middleware;

use App\Models\ApiCredential;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiCredential
{
    public function handle(Request $request, Closure $next): Response
    {
        [$keyId, $secret] = $this->extractCredentials($request);

        if (! $keyId || ! $secret) {
            return response()->json([
                'ok' => false,
                'error' => 'missing_credentials',
                'message' => 'Provide X-Api-Key and X-Api-Secret (or HTTP Basic key:secret).',
            ], 401);
        }

        /** @var ApiCredential|null $credential */
        $credential = ApiCredential::query()
            ->with('client')
            ->where('key_id', $keyId)
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->first();

        if (! $credential || ! Hash::check($secret, $credential->secret_hash)) {
            return response()->json([
                'ok' => false,
                'error' => 'invalid_credentials',
                'message' => 'Invalid API key or secret.',
            ], 401);
        }

        $client = $credential->client;
        if (! $client || ! $client->api_enabled || $client->status->value !== 'active') {
            return response()->json([
                'ok' => false,
                'error' => 'api_disabled',
                'message' => 'API access is disabled for this account.',
            ], 403);
        }

        if (is_array($credential->ip_allowlist) && $credential->ip_allowlist !== []) {
            if (! in_array($request->ip(), $credential->ip_allowlist, true)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'ip_not_allowed',
                    'message' => 'Request IP is not on the allowlist.',
                ], 403);
            }
        }

        $limitKey = 'api-cred:'.$credential->id;
        $max = max(1, (int) $credential->rate_limit_per_minute);
        if (RateLimiter::tooManyAttempts($limitKey, $max)) {
            return response()->json([
                'ok' => false,
                'error' => 'rate_limited',
                'message' => 'API rate limit exceeded.',
            ], 429);
        }
        RateLimiter::hit($limitKey, 60);

        $credential->forceFill(['last_used_at' => now()])->save();

        $request->attributes->set('api_credential', $credential);
        $request->attributes->set('api_client', $client);

        return $next($request);
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    protected function extractCredentials(Request $request): array
    {
        $key = $request->header('X-Api-Key');
        $secret = $request->header('X-Api-Secret');

        if ($key && $secret) {
            return [$key, $secret];
        }

        $auth = (string) $request->header('Authorization', '');
        if (str_starts_with($auth, 'Basic ')) {
            $decoded = base64_decode(substr($auth, 6), true) ?: '';
            if (str_contains($decoded, ':')) {
                return explode(':', $decoded, 2);
            }
        }

        return [null, null];
    }
}
