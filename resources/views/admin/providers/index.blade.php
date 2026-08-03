<x-portal-layout title="Providers" portalLabel="Administration" header="Providers & Routes" :nav="\App\Support\PortalNav::admin('providers')">
    <form method="POST" action="{{ route('admin.providers.store') }}" data-ajax class="table-wrap mb-4 grid gap-2 p-3 md:grid-cols-3" novalidate>
        @csrf
        <div data-form-error class="hidden md:col-span-3 rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-sm text-red-700"></div>
        <input name="name" class="input-field-sm" placeholder="Provider name">
        <select name="driver" class="input-field-sm">
            <option value="log">Log (dev)</option>
            <option value="jasmin">Jasmin HTTP</option>
        </select>
        <input type="number" name="rate_limit_per_second" class="input-field-sm" value="18">
        <input type="number" step="0.0001" name="cost_per_sms" class="input-field-sm" value="0.0200">
        <input type="number" step="0.01" name="balance" class="input-field-sm" placeholder="Provider balance">
        <input name="base_url" class="input-field-sm" placeholder="https://jasmin.example/">
        <input name="username" class="input-field-sm" placeholder="Username">
        <x-password-input name="password" size="sm" placeholder="Password" />
        <input name="webhook_url" class="input-field-sm md:col-span-2" placeholder="DLR webhook URL">
        <button class="btn-sm-primary" data-loading-text="Saving…">Add provider</button>
    </form>

    <div class="space-y-4">
        @foreach($providers as $provider)
            <section class="table-wrap p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">{{ $provider->name }}</h2>
                        <p class="text-sm text-slate-500">{{ $provider->driver }} · {{ $provider->rate_limit_per_second }}/s · cost {{ number_format($provider->cost_per_sms, 4) }}</p>
                    </div>
                    <div class="text-sm">Balance: <strong>{{ number_format((float)$provider->balance, 2) }}</strong> · Health: {{ $provider->health_status }}</div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach($provider->routes as $route)
                        <span class="badge bg-teal-50 text-teal-800">{{ $route->name }} ({{ $route->category->value }})</span>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-portal-layout>
