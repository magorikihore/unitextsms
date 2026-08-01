<x-portal-layout title="Admin" portalLabel="Administration" header="Platform Dashboard" subheader="Live client, traffic and billing overview" :nav="\App\Support\PortalNav::admin('dashboard')">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Total clients" :value="$totalClients" />
        <x-stat label="Active clients" :value="$activeClients" tone="ok" />
        <x-stat label="Suspended" :value="$suspendedClients" tone="warn" />
        <x-stat label="Queue size" :value="$queueSize" />
        <x-stat label="SMS today" :value="number_format($smsToday)" />
        <x-stat label="SMS this month" :value="number_format($smsMonth)" />
        <x-stat label="Delivered today" :value="number_format($delivered)" tone="ok" />
        <x-stat label="Failed today" :value="number_format($failed)" tone="bad" />
        <x-stat label="Delivery rate" :value="$deliveryRate.'%'" />
        <x-stat label="OTP avg speed" :value="($otpAvgMs ?: 0).' ms'" />
        <x-stat label="Provider balance" :value="number_format($providerBalance, 2)" />
        <x-stat label="Client balances" :value="number_format($clientBalances, 2)" />
        <x-stat label="Revenue (month)" :value="number_format($revenue, 2)" tone="ok" />
        <x-stat label="Provider cost" :value="number_format($providerCost, 2)" />
        <x-stat label="Gross profit" :value="number_format($profit, 2)" tone="ok" />
        <x-stat label="Pending today" :value="number_format($pending)" tone="warn" />
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section class="table-wrap p-5">
            <h2 class="text-lg font-semibold">System alerts</h2>
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
            <h2 class="text-lg font-semibold">Top client balances</h2>
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
