<x-portal-layout title="{{ $campaign->name }}" portalLabel="Administration" header="{{ $campaign->name }}" subheader="{{ $campaign->client?->company_name }} · {{ $campaign->status->value }}" :nav="\App\Support\PortalNav::admin('campaigns')">
    <div class="mb-6 flex flex-wrap gap-2">
        <a href="{{ route('admin.campaigns.index') }}" class="btn-secondary">Back to campaigns</a>
        @if(in_array($campaign->status, [\App\Enums\CampaignStatus::Processing, \App\Enums\CampaignStatus::Scheduled], true))
            <form method="POST" action="{{ route('admin.campaigns.pause', $campaign) }}">@csrf<button class="btn-secondary">Pause</button></form>
        @endif
        @if(! in_array($campaign->status, [\App\Enums\CampaignStatus::Completed, \App\Enums\CampaignStatus::Cancelled], true))
            <form method="POST" action="{{ route('admin.campaigns.cancel', $campaign) }}">@csrf<button class="btn-danger">Cancel</button></form>
        @endif
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Valid recipients" :value="number_format($campaign->valid_count)" />
        <x-stat label="Queued" :value="number_format($campaign->queued_count)" />
        <x-stat label="Delivered" :value="number_format($campaign->delivered_count)" tone="ok" />
        <x-stat label="Failed" :value="number_format($campaign->failed_count)" tone="bad" />
        <x-stat label="Delivery rate" :value="$campaign->deliveryRate().'%'" />
        <x-stat label="Estimated cost" :value="number_format($campaign->estimated_cost, 2)" />
        <x-stat label="Charged" :value="number_format($campaign->charged_amount, 2)" />
        <x-stat label="Sender" :value="$campaign->senderId?->sender ?: '—'" />
    </div>

    <section class="table-wrap mb-6 p-5 text-sm">
        <h2 class="mb-2 text-lg font-semibold">Message</h2>
        <p class="whitespace-pre-wrap text-slate-700">{{ $campaign->message_body }}</p>
    </section>

    <div class="table-wrap overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">Phone</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Units</th>
                <th class="px-4 py-3">Charge</th>
                <th class="px-4 py-3">Failure</th>
                <th class="px-4 py-3">When</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @foreach($messages as $message)
                <tr>
                    <td class="px-4 py-3">{{ $message->to_phone_normalized }}</td>
                    <td class="px-4 py-3">{{ $message->status->value }}</td>
                    <td class="px-4 py-3">{{ $message->units }}</td>
                    <td class="px-4 py-3">{{ number_format($message->client_charge, 4) }}</td>
                    <td class="px-4 py-3">{{ $message->failure_reason ?: '—' }}</td>
                    <td class="px-4 py-3">{{ $message->created_at->format('Y-m-d H:i') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $messages->links() }}</div>
</x-portal-layout>
