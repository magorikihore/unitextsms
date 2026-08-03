<x-portal-layout title="Dashboard" portalLabel="{{ $client->company_name }}" header="Client dashboard" subheader="Balance, delivery health and recent activity" icon="dashboard" :nav="\App\Support\PortalNav::client($client, 'dashboard')">
    @if($lowBalance)
        <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Low balance warning: {{ number_format($balance, 2) }} credits remaining (threshold {{ number_format($client->low_balance_threshold, 2) }}).
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat icon="balance" label="SMS balance" :value="number_format($balance, 2)" :tone="$lowBalance ? 'warn' : 'ok'" :tint="$lowBalance ? 'amber' : 'emerald'" />
        <x-stat icon="send" label="Sent today" :value="number_format($sentToday)" tint="sky" />
        <x-stat icon="chart" label="Sent this month" :value="number_format($sentMonth)" tint="teal" />
        <x-stat icon="chart" label="Delivery rate" :value="$deliveryRate.'%'" tint="violet" />
        <x-stat icon="delivered" label="Delivered" :value="number_format($delivered)" tone="ok" tint="emerald" />
        <x-stat icon="failed" label="Failed" :value="number_format($failed)" tone="bad" tint="rose" />
        <x-stat icon="pending" label="Pending" :value="number_format($pending)" tone="warn" tint="amber" />
        <x-stat icon="otp" label="OTP avg time" :value="($otpAvgMs ?: 0).' ms'" tint="slate" />
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section class="table-wrap p-5">
            <h2 class="flex items-center gap-2 font-semibold text-slate-900">
                <span class="text-teal-300"><x-icon name="chart" class="h-5 w-5" /></span>
                Delivery (7 days)
            </h2>
            <div class="mt-4 space-y-2">
                @foreach($chart as $day)
                    <div class="flex items-center gap-3 text-sm">
                        <div class="w-8 text-slate-500">{{ $day['label'] }}</div>
                        <div class="h-2 flex-1 overflow-hidden rounded bg-slate-100">
                            <div class="h-full bg-teal-600" style="width: {{ min(100, ($day['delivered'] + $day['failed']) ? ($day['delivered'] / max($day['delivered']+$day['failed'],1) * 100) : 0) }}%"></div>
                        </div>
                        <div class="w-24 text-right text-slate-600">{{ $day['delivered'] }} / {{ $day['failed'] }}</div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="table-wrap p-5">
            <h2 class="flex items-center gap-2 font-semibold text-slate-900">
                <span class="text-sky-300"><x-icon name="campaigns" class="h-5 w-5" /></span>
                Recent campaigns
            </h2>
            <div class="mt-3 space-y-2 text-sm">
                @forelse($recentCampaigns as $campaign)
                    <a href="{{ route('client.campaigns.show', [$client, $campaign]) }}" class="flex justify-between border-b border-slate-100 py-2 hover:text-teal-700">
                        <span>{{ $campaign->name }}</span>
                        <span class="badge bg-slate-100">{{ $campaign->status->value }}</span>
                    </a>
                @empty
                    <p class="text-slate-500">No campaigns yet.</p>
                @endforelse
            </div>
        </section>

        <section class="table-wrap p-5">
            <h2 class="flex items-center gap-2 font-semibold text-slate-900">
                <span class="text-violet-300"><x-icon name="calendar" class="h-5 w-5" /></span>
                Scheduled
            </h2>
            <div class="mt-3 space-y-2 text-sm">
                @forelse($scheduledCampaigns as $campaign)
                    <div class="flex justify-between border-b border-slate-100 py-2">
                        <span>{{ $campaign->name }}</span>
                        <span>{{ $campaign->scheduled_at?->format('Y-m-d H:i') }}</span>
                    </div>
                @empty
                    <p class="text-slate-500">Nothing scheduled.</p>
                @endforelse
            </div>
        </section>

        <section class="table-wrap p-5">
            <h2 class="flex items-center gap-2 font-semibold text-slate-900">
                <span class="text-slate-300"><x-icon name="activity" class="h-5 w-5" /></span>
                Account activity
            </h2>
            <div class="mt-3 space-y-2 text-sm">
                @forelse($activity as $log)
                    <div class="border-b border-slate-100 py-2">
                        <div class="font-mono text-xs text-slate-700">{{ $log->action }}</div>
                        <div class="text-xs text-slate-500">{{ $log->created_at->diffForHumans() }}</div>
                    </div>
                @empty
                    <p class="text-slate-500">No recent activity.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-portal-layout>
