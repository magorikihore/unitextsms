<x-guest-layout title="Forgot password">
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-slate-900" style="font-family:'Source Serif 4', Georgia, serif;">Forgot password?</h1>
        <p class="mt-1 text-sm text-slate-500">Enter your account email and we’ll send a reset link.</p>
    </div>

    <form method="POST" action="{{ route('password.email') }}" data-ajax class="space-y-4" novalidate>
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

        <button type="submit" data-loading-text="Sending…" class="btn-primary w-full justify-center py-2.5">
            Email reset link
        </button>

        <p class="text-center text-sm text-slate-500">
            <a href="{{ route('login') }}" class="font-medium text-teal-700 hover:text-teal-800">Back to sign in</a>
        </p>
    </form>
</x-guest-layout>
