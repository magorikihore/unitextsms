<x-portal-layout title="Traffic" portalLabel="Administration" header="SMS Traffic" subheader="Search and filter live queue traffic" icon="traffic" :nav="\App\Support\PortalNav::admin('traffic')">
    @if($bulkPaused)
        <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Bulk sending paused: {{ $bulkPaused }}
        </div>
    @endif

    @php $gw = $gatewayHealth ?? null; @endphp
    @if($gw && !($gw['healthy'] ?? true))
        <div class="mb-4 rounded-lg border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-900">
            Jasmin gateway {{ strtoupper($gw['status'] ?? 'down') }}:
            {{ $gw['http_error'] ?? (($gw['recent_gateway_failures'] ?? 0).' gateway failures in the last hour') }}
        </div>
    @endif

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach($queues as $name => $count)
            <x-stat :label="$name" :value="$count.' queued'" :hint="($rateLimits[$name] ?? '—').' TPS reserved'" />
        @endforeach
        <x-stat label="OTP avg latency" :value="$otpLatencyMs.' ms'" />
        @if($gw)
            <x-stat
                label="Jasmin gateway"
                :value="strtoupper($gw['status'] ?? '—')"
                :tone="($gw['healthy'] ?? false) ? 'ok' : 'bad'"
                :hint="($gw['http_ok'] ?? false) ? (($gw['http_ms'] ?? '—').' ms') : ($gw['http_error'] ?? 'down')"
            />
        @endif
    </div>

    <form method="GET" action="{{ route('admin.traffic.index') }}" class="table-wrap mb-4 p-3">
        <div class="toolbar-row">
            <input type="search"
                   name="phone"
                   value="{{ $filters['phone'] }}"
                   class="input-field-sm w-full max-w-xs"
                   placeholder="Phone number…"
                   inputmode="tel"
                   autocomplete="off">
            <x-client-search-select
                class="min-w-[14rem] max-w-xs"
                name="client_id"
                :clients="$clients"
                :selected="$filters['client_id'] ?: null"
                :allow-empty="true"
                empty-label="All clients"
                placeholder="Search client…"
            />
            <select name="queue" class="input-field-sm w-auto min-w-[10rem]">
                <option value="">All queues</option>
                @foreach(['sms-otp' => 'OTP', 'sms-transactional' => 'Transactional', 'sms-bulk' => 'Bulk'] as $value => $label)
                    <option value="{{ $value }}" @selected($filters['queue'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="status" class="input-field-sm w-auto min-w-[9rem]">
                <option value="">All statuses</option>
                @foreach(['queued','pending','submitted','delivered','failed','rejected','expired','cancelled'] as $status)
                    <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
                @endforeach
            </select>
            <button class="btn-sm-primary">Search</button>
            @if(request()->hasAny(['phone','client_id','queue','status']))
                <a href="{{ route('admin.traffic.index') }}" class="btn-sm-secondary">Clear</a>
            @endif
        </div>
    </form>

    <div class="table-wrap overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">UUID</th>
                <th class="px-4 py-3">Client</th>
                <th class="px-4 py-3">To</th>
                <th class="px-4 py-3">Sender</th>
                <th class="px-4 py-3">Queue</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Failure</th>
                <th class="px-4 py-3">When</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($messages as $message)
                <tr>
                    <td class="px-4 py-3 font-mono text-xs">{{ Str::limit($message->uuid, 13) }}</td>
                    <td class="px-4 py-3">{{ $message->client?->company_name }}</td>
                    <td class="px-4 py-3 font-mono">{{ $message->to_phone_normalized }}</td>
                    <td class="px-4 py-3">{{ $message->from_sender }}</td>
                    <td class="px-4 py-3">{{ $message->queue_name }}</td>
                    <td class="px-4 py-3">{{ $message->status->value }}</td>
                    <td class="px-4 py-3 text-sm {{ $message->failure_reason ? 'text-red-700' : 'text-slate-400' }}">
                        {{ \App\Support\SmsStatusPresenter::humanize($message->failure_reason) ?: '—' }}
                    </td>
                    <td class="px-4 py-3">{{ $message->created_at->diffForHumans() }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">
                        No messages match your filters.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $messages->links() }}</div>
</x-portal-layout>
