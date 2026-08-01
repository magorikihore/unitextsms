<x-portal-layout title="Providers" portalLabel="Administration" header="Providers & Routes" :nav="\App\Support\PortalNav::admin('providers')">
    <form method="POST" action="{{ route('admin.providers.store') }}" class="table-wrap mb-6 grid gap-3 p-5 md:grid-cols-3">
        @csrf
        <input name="name" class="input-field" placeholder="Provider name" required>
        <select name="driver" class="input-field">
            <option value="log">Log (dev)</option>
            <option value="jasmin">Jasmin HTTP</option>
        </select>
        <input type="number" name="rate_limit_per_second" class="input-field" value="18" required>
        <input type="number" step="0.0001" name="cost_per_sms" class="input-field" value="0.0200" required>
        <input type="number" step="0.01" name="balance" class="input-field" placeholder="Provider balance">
        <input name="base_url" class="input-field" placeholder="https://jasmin.example/">
        <input name="username" class="input-field" placeholder="Username">
        <input type="password" name="password" class="input-field" placeholder="Password">
        <input name="webhook_url" class="input-field md:col-span-2" placeholder="DLR webhook URL">
        <button class="btn-primary">Add provider</button>
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
