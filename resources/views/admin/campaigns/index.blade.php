<x-portal-layout title="Campaigns" portalLabel="Administration" header="Campaigns" subheader="Monitor and control campaigns across all clients" :nav="\App\Support\PortalNav::admin('campaigns')">
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
            <select name="status" class="input-field-sm w-auto min-w-[8rem]">
                <option value="">Status</option>
                @foreach($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? null)===$status->value)>{{ $status->value }}</option>
                @endforeach
            </select>
            <select name="category" class="input-field-sm w-auto min-w-[8rem]">
                <option value="">Category</option>
                @foreach(['otp' => 'OTP', 'transactional' => 'Transactional', 'alert' => 'Alert', 'marketing' => 'Bulk SMS'] as $cat => $catLabel)
                    <option value="{{ $cat }}" @selected(($filters['category'] ?? null)===$cat)>{{ $catLabel }}</option>
                @endforeach
            </select>
            <input name="q" class="input-field-sm w-44" placeholder="Search name" value="{{ $filters['q'] ?? '' }}">
            <button class="btn-sm-primary">Filter</button>
            @if(request()->hasAny(['client_id','status','category','q']))
                <a href="{{ route('admin.campaigns.index') }}" class="btn-sm-secondary">Clear</a>
            @endif
        </div>
    </form>

    <div class="table-wrap overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr>
                <th class="px-3 py-2">Campaign</th>
                <th class="px-3 py-2">Client</th>
                <th class="px-3 py-2">Status</th>
                <th class="px-3 py-2">Category</th>
                <th class="px-3 py-2">Valid</th>
                <th class="px-3 py-2">Delivered</th>
                <th class="px-3 py-2">Failed</th>
                <th class="px-3 py-2">Cost</th>
                <th class="px-3 py-2">Actions</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($campaigns as $campaign)
                <tr class="hover:bg-slate-50/70">
                    <td class="px-3 py-2">
                        <a class="font-semibold text-blue-700 hover:underline" href="{{ route('admin.campaigns.show', $campaign) }}">{{ $campaign->name }}</a>
                        <div class="text-xs text-slate-500">{{ $campaign->created_at?->format('Y-m-d H:i') }}</div>
                    </td>
                    <td class="px-3 py-2">{{ $campaign->client?->company_name }}</td>
                    <td class="px-3 py-2">{{ $campaign->status->value }}</td>
                    <td class="px-3 py-2">{{ $campaign->category->value }}</td>
                    <td class="px-3 py-2">{{ $campaign->valid_count }}</td>
                    <td class="px-3 py-2">{{ $campaign->delivered_count }}</td>
                    <td class="px-3 py-2">{{ $campaign->failed_count }}</td>
                    <td class="px-3 py-2">{{ number_format($campaign->charged_amount ?: $campaign->estimated_cost, 2) }}</td>
                    <td class="px-3 py-2">
                        <div class="flex flex-wrap gap-1">
                            @if(in_array($campaign->status, [\App\Enums\CampaignStatus::Processing, \App\Enums\CampaignStatus::Scheduled], true))
                                <form method="POST" action="{{ route('admin.campaigns.pause', $campaign) }}">@csrf<button class="btn-sm-secondary">Pause</button></form>
                            @endif
                            @if(! in_array($campaign->status, [\App\Enums\CampaignStatus::Completed, \App\Enums\CampaignStatus::Cancelled], true))
                                <form method="POST" action="{{ route('admin.campaigns.cancel', $campaign) }}">@csrf<button class="btn-sm-secondary">Cancel</button></form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="px-3 py-8 text-center text-slate-500">No campaigns found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $campaigns->links() }}</div>
</x-portal-layout>
