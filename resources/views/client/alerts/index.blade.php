<x-portal-layout title="System messages" portalLabel="{{ $client->company_name }}" header="System messages" subheader="Platform notices for your account" :nav="\App\Support\PortalNav::client($client, 'alerts')">
    <div class="space-y-3">
        @forelse($alerts as $alert)
            <div class="table-wrap p-4">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="badge
                        {{ $alert->severity === 'critical' ? 'bg-red-100 text-red-800' : ($alert->severity === 'warning' ? 'bg-amber-100 text-amber-900' : 'bg-slate-100 text-slate-700') }}
                    ">{{ $alert->severity }}</span>
                    <span class="text-xs uppercase text-slate-500">{{ $alert->type }}</span>
                    <span class="text-xs text-slate-400">{{ $alert->created_at->diffForHumans() }}</span>
                </div>
                <h2 class="mt-2 font-semibold text-slate-900">{{ $alert->title }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ $alert->body }}</p>
            </div>
        @empty
            <div class="table-wrap p-8 text-center text-sm text-slate-500">No system messages right now.</div>
        @endforelse
    </div>
    <div class="mt-3">{{ $alerts->links() }}</div>
</x-portal-layout>
