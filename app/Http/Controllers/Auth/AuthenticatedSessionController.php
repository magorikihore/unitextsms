<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse|JsonResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();

        // Platform staff always land on the admin console — never a client portal
        // (session "intended" URLs from /portal/{client} were confusing admins).
        if ($user?->isPlatformUser()) {
            $redirect = route('admin.dashboard');
        } else {
            $redirect = redirect()->intended(route('dashboard', absolute: false))->getTargetUrl();
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Signed in successfully.',
                'redirect' => $redirect,
            ]);
        }

        return redirect()->to($redirect);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse|JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Signed out.',
                'redirect' => route('login'),
            ]);
        }

        return redirect('/');
    }
}
