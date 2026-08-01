<x-portal-layout title="Campaigns" portalLabel="{{ $client->company_name }}" header="Campaigns" :nav="\App\Support\PortalNav::client($client, 'campaigns')">
    <div class="mb-4"><a href="{{ route('client.send.create', $client) }}" class="btn-primary">New campaign</a></div>
    <div class="table-wrap overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">Name</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Valid</th>
                <th class="px-4 py-3">Delivered</th>
                <th class="px-4 py-3">Failed</th>
                <th class="px-4 py-3">Cost</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @foreach($campaigns as $campaign)
                <tr>
                    <td class="px-4 py-3"><a class="font-semibold text-teal-700 hover:underline" href="{{ route('client.campaigns.show', [$client, $campaign]) }}">{{ $campaign->name }}</a></td>
                    <td class="px-4 py-3">{{ $campaign->status->value }}</td>
                    <td class="px-4 py-3">{{ $campaign->valid_count }}</td>
                    <td class="px-4 py-3">{{ $campaign->delivered_count }}</td>
                    <td class="px-4 py-3">{{ $campaign->failed_count }}</td>
                    <td class="px-4 py-3">{{ number_format($campaign->charged_amount ?: $campaign->estimated_cost, 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $campaigns->links() }}</div>
</x-portal-layout>
