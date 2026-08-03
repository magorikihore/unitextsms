<x-guest-layout title="Reset password">
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-slate-900" style="font-family:'Source Serif 4', Georgia, serif;">Set a new password</h1>
        <p class="mt-1 text-sm text-slate-500">Choose a strong password for your UniNText account.</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}" data-ajax class="space-y-4" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div data-form-error class="hidden rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></div>
        <div data-form-success class="hidden rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700"></div>

        <div>
            <label for="email" class="text-sm font-medium text-slate-700">Email address</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email', $request->email) }}"
                required
                autofocus
                autocomplete="username"
                class="input-field mt-1.5"
            >
            <p data-error-for="email" class="mt-1.5 hidden text-sm text-red-600"></p>
        </div>

        <div>
            <label for="password" class="text-sm font-medium text-slate-700">New password</label>
            <x-password-input name="password" class="mt-1.5" required autocomplete="new-password" placeholder="••••••••" />
            <p data-error-for="password" class="mt-1.5 hidden text-sm text-red-600"></p>
        </div>

        <div>
            <label for="password_confirmation" class="text-sm font-medium text-slate-700">Confirm password</label>
            <x-password-input name="password_confirmation" class="mt-1.5" required autocomplete="new-password" placeholder="••••••••" />
            <p data-error-for="password_confirmation" class="mt-1.5 hidden text-sm text-red-600"></p>
        </div>

        <button type="submit" data-loading-text="Saving…" class="btn-primary w-full justify-center py-2.5">
            Reset password
        </button>

        <p class="text-center text-sm text-slate-500">
            <a href="{{ route('login') }}" class="font-medium text-teal-700 hover:text-teal-800">Back to sign in</a>
        </p>
    </form>
</x-guest-layout>
