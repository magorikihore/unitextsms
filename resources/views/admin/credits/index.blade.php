<x-portal-layout title="Credits" portalLabel="Administration" header="Credits & Billing" subheader="Immutable ledger — never edit balances directly" :nav="\App\Support\PortalNav::admin('credits')">
    <form method="POST" action="{{ route('admin.credits.store') }}" class="table-wrap mb-6 grid gap-3 p-5 sm:grid-cols-5">
        @csrf
        <select name="client_id" class="input-field" required>
            <option value="">Select client</option>
            @foreach($clients as $client)
                <option value="{{ $client->id }}">{{ $client->company_name }} ({{ number_format($client->sms_balance, 2) }})</option>
            @endforeach
        </select>
        <select name="action" class="input-field">
            <option value="credit">Add credit</option>
            <option value="debit">Deduct</option>
        </select>
        <input type="number" step="0.01" min="0.01" name="amount" class="input-field" placeholder="Amount" required>
        <input name="reason" class="input-field" placeholder="Reason" required>
        <button class="btn-primary">Record ledger entry</button>
    </form>

    <div class="table-wrap overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">When</th>
                <th class="px-4 py-3">Client</th>
                <th class="px-4 py-3">Type</th>
                <th class="px-4 py-3">Amount</th>
                <th class="px-4 py-3">Before → After</th>
                <th class="px-4 py-3">Reason</th>
                <th class="px-4 py-3">Reference</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @foreach($entries as $entry)
                <tr>
                    <td class="px-4 py-3">{{ $entry->created_at->format('Y-m-d H:i') }}</td>
                    <td class="px-4 py-3">{{ $entry->client?->company_name }}</td>
                    <td class="px-4 py-3">{{ $entry->type->value }}</td>
                    <td class="px-4 py-3">{{ number_format($entry->amount, 2) }}</td>
                    <td class="px-4 py-3">{{ number_format($entry->balance_before, 2) }} → {{ number_format($entry->balance_after, 2) }}</td>
                    <td class="px-4 py-3">{{ $entry->reason }}</td>
                    <td class="px-4 py-3 font-mono text-xs">{{ $entry->reference }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $entries->links() }}</div>
</x-portal-layout>
