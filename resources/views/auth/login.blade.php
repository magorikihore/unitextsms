<x-guest-layout title="Sign in">
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-slate-900" style="font-family:'Source Serif 4', Georgia, serif;">Welcome back</h1>
        <p class="mt-1 text-sm text-slate-500">Enter your credentials to access the portal.</p>
    </div>

    <form method="POST" action="{{ route('login') }}" data-ajax class="space-y-4" novalidate>
        @csrf

        <div data-form-error class="hidden rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></div>
        <div data-form-success class="hidden rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700"></div>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        <div>
            <label for="email" class="text-sm font-medium text-slate-700">Email address</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="username"
                placeholder="you@company.com"
                class="input-field mt-1.5"
            >
            <p data-error-for="email" class="mt-1.5 hidden text-sm text-red-600"></p>
        </div>

        <div>
            <div class="flex items-center justify-between">
                <label for="password" class="text-sm font-medium text-slate-700">Password</label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-xs font-medium text-teal-700 hover:text-teal-800">Forgot password?</a>
                @endif
            </div>
            <x-password-input
                name="password"
                class="mt-1.5"
                required
                autocomplete="current-password"
                placeholder="••••••••"
            />
            <p data-error-for="password" class="mt-1.5 hidden text-sm text-red-600"></p>
        </div>

        <label class="inline-flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" class="rounded border-slate-300 text-teal-700 focus:ring-teal-600">
            Keep me signed in
        </label>

        <button type="submit" data-loading-text="Signing in…" class="btn-primary w-full justify-center py-2.5">
            Sign in
        </button>
    </form>
</x-guest-layout>
