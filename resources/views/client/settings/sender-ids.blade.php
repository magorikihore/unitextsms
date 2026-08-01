<x-portal-layout title="Sender IDs" portalLabel="{{ $client->company_name }}" header="Sender IDs" :nav="\App\Support\PortalNav::client($client, 'sender-ids')">
    <form method="POST" action="{{ route('client.sender-ids.store', $client) }}" class="table-wrap mb-6 grid gap-3 p-5 md:grid-cols-3">
        @csrf
        <input name="sender" class="input-field" maxlength="11" placeholder="SENDER" required>
        <select name="category" class="input-field">
            <option value="">Any category</option>
            <option value="otp">OTP</option>
            <option value="transactional">Transactional</option>
            <option value="marketing">Marketing</option>
        </select>
        <button class="btn-primary">Submit for approval</button>
    </form>

    <div class="table-wrap overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">Sender</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Category</th>
                <th class="px-4 py-3">Expires</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @foreach($senderIds as $sid)
                <tr>
                    <td class="px-4 py-3 font-semibold">{{ $sid->sender }}</td>
                    <td class="px-4 py-3">{{ $sid->status->value }}</td>
                    <td class="px-4 py-3">{{ $sid->category?->value ?: 'any' }}</td>
                    <td class="px-4 py-3">{{ $sid->expires_at?->toDateString() ?: '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-portal-layout>
