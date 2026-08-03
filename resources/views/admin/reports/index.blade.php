<x-portal-layout title="Reports" portalLabel="Administration" header="Delivery reports" :nav="\App\Support\PortalNav::admin('reports')">
    <form method="GET" class="table-wrap mb-4 p-3">
        <div class="toolbar-row">
            <x-client-search-select
                class="min-w-[14rem] max-w-xs"
                name="client_id"
                :clients="$clients"
                :selected="$filters['client_id'] ?? null"
                :allow-empty="true"
                empty-label="All clients"
                placeholder="Search client…"
            />
            <select name="provider_id" class="input-field-sm w-auto min-w-[9rem]">
                <option value="">All providers</option>
                @foreach($providers as $provider)
                    <option value="{{ $provider->id }}" @selected(($filters['provider_id'] ?? null)==$provider->id)>{{ $provider->name }}</option>
                @endforeach
            </select>
            <select name="category" class="input-field-sm w-auto min-w-[8rem]">
                <option value="">Category</option>
                @foreach(['otp' => 'OTP', 'transactional' => 'Transactional', 'alert' => 'Alert', 'marketing' => 'Bulk SMS'] as $cat => $catLabel)
                    <option value="{{ $cat }}" @selected(($filters['category'] ?? null)===$cat)>{{ $catLabel }}</option>
                @endforeach
            </select>
            <select name="status" class="input-field-sm w-auto min-w-[8rem]">
                <option value="">Status</option>
                @foreach(['queued','submitted','delivered','failed','rejected','expired'] as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? null)===$status)>{{ $status }}</option>
                @endforeach
            </select>
            <input type="date" name="from" class="input-field-sm w-auto" value="{{ $filters['from'] ?? '' }}">
            <input type="date" name="to" class="input-field-sm w-auto" value="{{ $filters['to'] ?? '' }}">
            <input name="phone" class="input-field-sm w-32" placeholder="Phone" value="{{ $filters['phone'] ?? '' }}">
            <input name="sender" class="input-field-sm w-28" placeholder="Sender" value="{{ $filters['sender'] ?? '' }}">
            <button class="btn-sm-primary">Apply</button>
            <a class="btn-sm-secondary" href="{{ route('admin.reports.export', request()->query()) }}">Export CSV</a>
        </div>
    </form>

    <div class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Submitted" :value="number_format($stats['submitted'])" />
        <x-stat label="Delivered" :value="number_format($stats['delivered'])" tone="ok" />
        <x-stat label="Failed" :value="number_format($stats['failed'])" tone="bad" />
        <x-stat label="Delivery %" :value="$stats['delivery_pct'].'%'" />
        <x-stat label="SMS units" :value="number_format($stats['units'])" />
        <x-stat label="Client charge" :value="number_format($stats['client_charge'], 2)" />
        <x-stat label="Provider cost" :value="number_format($stats['provider_cost'], 2)" />
        <x-stat label="Gross profit" :value="number_format($stats['gross_profit'], 2)" tone="ok" />
    </div>

    <div class="table-wrap overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr>
                <th class="px-3 py-2">When</th>
                <th class="px-3 py-2">Client</th>
                <th class="px-3 py-2">To</th>
                <th class="px-3 py-2">Sender</th>
                <th class="px-3 py-2">Category</th>
                <th class="px-3 py-2">Status</th>
                <th class="px-3 py-2">Charge</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @foreach($messages as $message)
                <tr class="hover:bg-slate-50/70">
                    <td class="whitespace-nowrap px-3 py-2">{{ $message->created_at->format('Y-m-d H:i') }}</td>
                    <td class="px-3 py-2">{{ $message->client?->company_name }}</td>
                    <td class="px-3 py-2">{{ $message->to_phone_normalized }}</td>
                    <td class="px-3 py-2">{{ $message->from_sender }}</td>
                    <td class="px-3 py-2">{{ $message->category->value }}</td>
                    <td class="px-3 py-2">{{ $message->status->value }}</td>
                    <td class="px-3 py-2">{{ number_format($message->client_charge, 4) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $messages->links() }}</div>
</x-portal-layout>
