<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            abort(403);
        }

        if ($user->isPlatformUser()) {
            return $next($request);
        }

        $client = $request->route('client');

        if ($client instanceof Client) {
            $membership = $user->clients()
                ->where('clients.id', $client->id)
                ->wherePivot('is_active', true)
                ->first();

            if (! $membership) {
                abort(403, 'You do not have access to this organisation.');
            }

            $request->attributes->set('client_role', $membership->pivot->role);
        }

        return $next($request);
    }
}
