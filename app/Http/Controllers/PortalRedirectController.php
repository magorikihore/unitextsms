<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PortalRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user?->isPlatformUser()) {
            return redirect()->route('admin.dashboard');
        }

        $client = $user?->clients()->wherePivot('is_active', true)->first();

        if ($client) {
            return redirect()->route('client.dashboard', $client);
        }

        abort(403, 'No organisation access configured for this account.');
    }
}
