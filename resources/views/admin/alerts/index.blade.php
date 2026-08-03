<x-portal-layout title="Alerts" portalLabel="Administration" header="Alerts" subheader="{{ $openCount }} open system alerts" :nav="\App\Support\PortalNav::admin('alerts')">
    <form method="POST" action="{{ route('admin.alerts.store') }}" data-ajax class="table-wrap mb-4 p-3" novalidate>
        @csrf
        <div data-form-error class="hidden mb-2 rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-sm text-red-700"></div>
        <div class="toolbar-row">
            <x-client-search-select
                class="min-w-[14rem] max-w-xs"
                name="client_id"
                :clients="$clients"
                :allow-empty="true"
                empty-label="Platform-wide"
                placeholder="Search client (optional)…"
            />
            <select name="severity" class="input-field-sm w-auto min-w-[8rem]" required>
                <option value="info">Info</option>
                <option value="warning" selected>Warning</option>
                <option value="critical">Critical</option>
            </select>
            <input name="type" class="input-field-sm w-36" placeholder="Type" required>
            <input name="title" class="input-field-sm min-w-[12rem] flex-1" placeholder="Title" required>
            <button type="submit" data-loading-text="Saving…" class="btn-sm-primary">Create alert</button>
        </div>
        <textarea name="body" class="input-field-sm mt-2" rows="2" placeholder="Alert details" required></textarea>
    </form>

    <div class="mb-3 toolbar-row">
        <form method="GET" class="toolbar-row">
            <select name="resolved" class="input-field-sm w-auto">
                <option value="0" @selected(!($filters['resolved'] ?? false) || ($filters['resolved'] ?? null)==='0')>Open</option>
                <option value="1" @selected(($filters['resolved'] ?? null)==='1' || ($filters['resolved'] ?? null)===1 || ($filters['resolved'] ?? null)===true)>Resolved</option>
            </select>
            <select name="severity" class="input-field-sm w-auto">
                <option value="">All severities</option>
                @foreach(['info','warning','critical'] as $severity)
                    <option value="{{ $severity }}" @selected(($filters['severity'] ?? null)===$severity)>{{ $severity }}</option>
                @endforeach
            </select>
            <button class="btn-sm-secondary">Filter</button>
        </form>
    </div>

    <div class="space-y-2">
        @forelse($alerts as $alert)
            <div class="table-wrap p-3">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="badge
                                {{ $alert->severity === 'critical' ? 'bg-red-100 text-red-800' : ($alert->severity === 'warning' ? 'bg-amber-100 text-amber-900' : 'bg-slate-100 text-slate-700') }}
                            ">{{ $alert->severity }}</span>
                            <span class="text-xs uppercase text-slate-500">{{ $alert->type }}</span>
                            @if($alert->client)
                                <span class="text-xs text-slate-500">· {{ $alert->client->company_name }}</span>
                            @endif
                        </div>
                        <h2 class="mt-1 font-semibold text-slate-900">{{ $alert->title }}</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ $alert->body }}</p>
                        <p class="mt-1 text-xs text-slate-400">{{ $alert->created_at->format('Y-m-d H:i') }}
                            @if($alert->is_resolved)
                                · resolved {{ $alert->resolved_at?->diffForHumans() }}
                            @endif
                        </p>
                    </div>
                    @unless($alert->is_resolved)
                        <form method="POST" action="{{ route('admin.alerts.resolve', $alert) }}">
                            @csrf
                            <button class="btn-sm-primary">Resolve</button>
                        </form>
                    @endunless
                </div>
            </div>
        @empty
            <div class="table-wrap p-6 text-sm text-slate-500">No alerts in this filter.</div>
        @endforelse
    </div>
    <div class="mt-3">{{ $alerts->links() }}</div>
</x-portal-layout>
