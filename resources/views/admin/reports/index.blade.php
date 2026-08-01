<x-portal-layout title="Reports" portalLabel="Administration" header="Delivery reports" :nav="\App\Support\PortalNav::admin('reports')">
    <form method="GET" class="table-wrap mb-6 grid gap-3 p-5 md:grid-cols-4">
        <select name="client_id" class="input-field">
            <option value="">All clients</option>
            @foreach($clients as $client)
                <option value="{{ $client->id }}" @selected(($filters['client_id'] ?? null)==$client->id)>{{ $client->company_name }}</option>
            @endforeach
        </select>
        <select name="provider_id" class="input-field">
            <option value="">All providers</option>
            @foreach($providers as $provider)
                <option value="{{ $provider->id }}" @selected(($filters['provider_id'] ?? null)==$provider->id)>{{ $provider->name }}</option>
            @endforeach
        </select>
        <select name="category" class="input-field">
            <option value="">All categories</option>
            @foreach(['otp','transactional','alert','marketing'] as $cat)
                <option value="{{ $cat }}" @selected(($filters['category'] ?? null)===$cat)>{{ $cat }}</option>
            @endforeach
        </select>
        <select name="status" class="input-field">
            <option value="">All statuses</option>
            @foreach(['queued','submitted','delivered','failed','rejected','expired'] as $status)
                <option value="{{ $status }}" @selected(($filters['status'] ?? null)===$status)>{{ $status }}</option>
            @endforeach
        </select>
        <input type="date" name="from" class="input-field" value="{{ $filters['from'] ?? '' }}">
        <input type="date" name="to" class="input-field" value="{{ $filters['to'] ?? '' }}">
        <input name="phone" class="input-field" placeholder="Phone" value="{{ $filters['phone'] ?? '' }}">
        <input name="sender" class="input-field" placeholder="Sender ID" value="{{ $filters['sender'] ?? '' }}">
        <button class="btn-primary">Apply filters</button>
        <a class="btn-secondary text-center" href="{{ route('admin.reports.export', request()->query()) }}">Export CSV</a>
    </form>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
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
                <th class="px-4 py-3">When</th>
                <th class="px-4 py-3">Client</th>
                <th class="px-4 py-3">To</th>
                <th class="px-4 py-3">Sender</th>
                <th class="px-4 py-3">Category</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Charge</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @foreach($messages as $message)
                <tr>
                    <td class="px-4 py-3">{{ $message->created_at->format('Y-m-d H:i') }}</td>
                    <td class="px-4 py-3">{{ $message->client?->company_name }}</td>
                    <td class="px-4 py-3">{{ $message->to_phone_normalized }}</td>
                    <td class="px-4 py-3">{{ $message->from_sender }}</td>
                    <td class="px-4 py-3">{{ $message->category->value }}</td>
                    <td class="px-4 py-3">{{ $message->status->value }}</td>
                    <td class="px-4 py-3">{{ number_format($message->client_charge, 4) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $messages->links() }}</div>
</x-portal-layout>
