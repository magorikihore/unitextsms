<x-portal-layout title="Reports" portalLabel="{{ $client->company_name }}" header="Delivery reports" :nav="\App\Support\PortalNav::client($client, 'reports')">
    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <select name="status" class="input-field max-w-[10rem]">
            <option value="">Status</option>
            @foreach(['delivered','failed','queued','submitted','expired'] as $status)
                <option value="{{ $status }}" @selected(request('status')===$status)>{{ $status }}</option>
            @endforeach
        </select>
        <select name="category" class="input-field max-w-[10rem]">
            <option value="">Category</option>
            @foreach(['otp' => 'OTP', 'transactional' => 'Transactional', 'alert' => 'Alert', 'marketing' => 'Bulk SMS'] as $cat => $catLabel)
                <option value="{{ $cat }}" @selected(request('category')===$cat)>{{ $catLabel }}</option>
            @endforeach
        </select>
        <input type="date" name="from" class="input-field max-w-[12rem]" value="{{ request('from') }}">
        <input type="date" name="to" class="input-field max-w-[12rem]" value="{{ request('to') }}">
        <button class="btn-secondary">Filter</button>
    </form>
    <div class="table-wrap overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">When</th>
                <th class="px-4 py-3">To</th>
                <th class="px-4 py-3">Sender</th>
                <th class="px-4 py-3">Category</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Failure</th>
                <th class="px-4 py-3">Units</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @foreach($messages as $message)
                <tr>
                    <td class="px-4 py-3">{{ $message->created_at->format('Y-m-d H:i') }}</td>
                    <td class="px-4 py-3">{{ $message->to_phone_normalized }}</td>
                    <td class="px-4 py-3">{{ $message->from_sender }}</td>
                    <td class="px-4 py-3">{{ $message->category->value }}</td>
                    <td class="px-4 py-3">
                        <span class="{{ $message->status->value === 'failed' || $message->status->value === 'rejected' ? 'text-red-700 font-medium' : '' }}">
                            {{ $message->status->value }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm {{ $message->failure_reason ? 'text-red-700' : 'text-slate-400' }}">
                        {{ \App\Support\SmsStatusPresenter::humanize($message->failure_reason) ?: '—' }}
                    </td>
                    <td class="px-4 py-3">{{ $message->units }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $messages->links() }}</div>
</x-portal-layout>
