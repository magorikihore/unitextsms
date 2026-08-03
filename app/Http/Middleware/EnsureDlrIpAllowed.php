<?php

namespace App\Http\Middleware;

use App\Support\IpAllowList;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureDlrIpAllowed
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = IpAllowList::parse(config('sms.dlr_allowed_ips', []));

        // Non-production: empty list keeps local/tests working.
        if ($allowed === [] && ! app()->environment('production')) {
            return $next($request);
        }

        $ip = (string) $request->ip();

        if ($allowed === [] || ! IpAllowList::allows($ip, $allowed)) {
            Log::warning('DLR request blocked by IP allow list', [
                'ip' => $ip,
                'path' => $request->path(),
            ]);

            return response('Forbidden', 403);
        }

        return $next($request);
    }
}
