<x-portal-layout title="Billing" portalLabel="{{ $client->company_name }}" header="Billing" subheader="Balance {{ number_format($client->sms_balance, 2) }} · {{ $client->billing_type->value }}" :nav="\App\Support\PortalNav::client($client, 'billing')">
    <div class="table-wrap overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">When</th>
                <th class="px-4 py-3">Type</th>
                <th class="px-4 py-3">Amount</th>
                <th class="px-4 py-3">Balance after</th>
                <th class="px-4 py-3">Reason</th>
                <th class="px-4 py-3">Reference</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @foreach($entries as $entry)
                <tr>
                    <td class="px-4 py-3">{{ $entry->created_at->format('Y-m-d H:i') }}</td>
                    <td class="px-4 py-3">{{ $entry->type->value }}</td>
                    <td class="px-4 py-3">{{ number_format($entry->amount, 2) }}</td>
                    <td class="px-4 py-3">{{ number_format($entry->balance_after, 2) }}</td>
                    <td class="px-4 py-3">{{ $entry->reason }}</td>
                    <td class="px-4 py-3 font-mono text-xs">{{ $entry->reference }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $entries->links() }}</div>
</x-portal-layout>
