<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status !== Password::RESET_LINK_SENT) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => __($status),
                    'errors' => ['email' => [__($status)]],
                ], 422);
            }

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
        }

        $message = __($status);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => $message,
            ]);
        }

        return back()->with('status', $message);
    }
}
