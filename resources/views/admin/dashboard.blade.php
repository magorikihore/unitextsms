<x-portal-layout title="Admin" portalLabel="Administration" header="Platform Dashboard" subheader="Live client, traffic and billing overview" icon="dashboard" :nav="\App\Support\PortalNav::admin('dashboard')">
    @php
        $gw = $gatewayHealth ?? ['status' => 'unknown', 'healthy' => true];
        $gwTone = ($gw['status'] ?? '') === 'ok' ? 'ok' : (($gw['status'] ?? '') === 'degraded' ? 'warn' : 'bad');
        $gwTint = ($gw['status'] ?? '') === 'ok' ? 'emerald' : (($gw['status'] ?? '') === 'degraded' ? 'amber' : 'rose');
        $gwHint = ($gw['http_ok'] ?? false)
            ? (($gw['http_ms'] ?? '—').' ms · '.($gw['recent_gateway_failures'] ?? 0).' gateway fails/hr')
            : ($gw['http_error'] ?? 'unreachable');
    @endphp
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat icon="providers" label="Jasmin gateway" :value="strtoupper($gw['status'] ?? '—')" :tone="$gwTone" :tint="$gwTint" :hint="$gwHint" />
        <x-stat icon="clients" label="Total clients" :value="$totalClients" tint="sky" />
        <x-stat icon="clients" label="Active clients" :value="$activeClients" tone="ok" tint="emerald" />
        <x-stat icon="clients" label="Suspended" :value="$suspendedClients" tone="warn" tint="amber" />
        <x-stat icon="queue" label="Queue size" :value="$queueSize" tint="violet" />
        <x-stat icon="send" label="SMS today" :value="number_format($smsToday)" tint="teal" />
        <x-stat icon="chart" label="SMS this month" :value="number_format($smsMonth)" tint="sky" />
        <x-stat icon="delivered" label="Delivered today" :value="number_format($delivered)" tone="ok" tint="emerald" />
        <x-stat icon="failed" label="Failed today" :value="number_format($failed)" tone="bad" tint="rose" />
        <x-stat icon="chart" label="Delivery rate" :value="$deliveryRate.'%'" tint="teal" />
        <x-stat icon="otp" label="OTP avg speed" :value="($otpAvgMs ?: 0).' ms'" tint="violet" />
        <x-stat icon="providers" label="Provider balance" :value="number_format($providerBalance, 2)" tint="sky" />
        <x-stat icon="balance" label="Client balances" :value="number_format($clientBalances, 2)" tint="slate" />
        <x-stat icon="revenue" label="Revenue (month)" :value="number_format($revenue, 2)" tone="ok" tint="emerald" />
        <x-stat icon="payments" label="Provider cost" :value="number_format($providerCost, 2)" tint="amber" />
        <x-stat icon="revenue" label="Gross profit" :value="number_format($profit, 2)" tone="ok" tint="teal" />
        <x-stat icon="pending" label="Pending today" :value="number_format($pending)" tone="warn" tint="amber" />
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section class="table-wrap p-5">
            <h2 class="flex items-center gap-2 text-lg font-semibold text-slate-900">
                <span class="text-amber-300"><x-icon name="alerts" class="h-5 w-5" /></span>
                System alerts
            </h2>
            <div class="mt-4 space-y-3">
                @forelse($alerts as $alert)
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm">
                        <div class="font-semibold text-amber-900">{{ $alert->title }}</div>
                        <div class="text-amber-800">{{ $alert->body }}</div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No open alerts.</p>
                @endforelse
            </div>
        </section>

        <section class="table-wrap p-5">
            <h2 class="flex items-center gap-2 text-lg font-semibold text-slate-900">
                <span class="text-teal-300"><x-icon name="balance" class="h-5 w-5" /></span>
                Top client balances
            </h2>
            <div class="mt-4 divide-y divide-slate-100">
                @foreach($topClients as $c)
                    <div class="flex items-center justify-between py-2 text-sm">
                        <a class="font-medium text-teal-700 hover:underline" href="{{ route('admin.clients.show', $c) }}">{{ $c->company_name }}</a>
                        <span>{{ number_format($c->sms_balance, 2) }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-portal-layout>
