<x-portal-layout title="{{ $campaign->name }}" portalLabel="{{ $client->company_name }}" header="{{ $campaign->name }}" subheader="{{ $campaign->status->value }} · {{ $campaign->category->value }}" :nav="\App\Support\PortalNav::client($client, 'campaigns')">
    <div class="mb-4 flex flex-wrap gap-2">
        @if(in_array($campaign->status->value, ['processing','scheduled']))
            <form method="POST" action="{{ route('client.campaigns.pause', [$client, $campaign]) }}">@csrf<button class="btn-secondary">Pause</button></form>
            <form method="POST" action="{{ route('client.campaigns.cancel', [$client, $campaign]) }}">@csrf<button class="btn-danger">Cancel</button></form>
        @endif
        <form method="POST" action="{{ route('client.campaigns.duplicate', [$client, $campaign]) }}">@csrf<button class="btn-secondary">Duplicate</button></form>
        <a href="{{ route('client.campaigns.export', [$client, $campaign]) }}" class="btn-secondary">Download results</a>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Queued" :value="$campaign->queued_count" />
        <x-stat label="Sent" :value="$campaign->sent_count" />
        <x-stat label="Delivered" :value="$campaign->delivered_count" tone="ok" />
        <x-stat label="Failed" :value="$campaign->failed_count" tone="bad" />
        <x-stat label="Delivery rate" :value="$campaign->deliveryRate().'%'" />
        <x-stat label="Charged" :value="number_format($campaign->charged_amount, 2)" />
        <x-stat label="Invalid" :value="$campaign->invalid_count" />
        <x-stat label="Opted out" :value="$campaign->opted_out_count" />
    </div>

    <div class="table-wrap mt-8 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">Phone</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Units</th>
                <th class="px-4 py-3">Failure</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @foreach($messages as $message)
                <tr>
                    <td class="px-4 py-3">{{ $message->to_phone_normalized }}</td>
                    <td class="px-4 py-3">{{ $message->status->value }}</td>
                    <td class="px-4 py-3">{{ $message->units }}</td>
                    <td class="px-4 py-3">{{ $message->failure_reason }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $messages->links() }}</div>
</x-portal-layout>
