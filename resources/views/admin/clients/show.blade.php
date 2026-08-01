<x-portal-layout title="{{ $client->company_name }}" portalLabel="Administration" header="{{ $client->company_name }}" subheader="{{ $client->contact_email }} · {{ $client->status->value }}" :nav="\App\Support\PortalNav::admin('clients')">
    <div class="mb-4 flex flex-wrap gap-2">
        @if($client->status->value !== 'active')
            <form method="POST" action="{{ route('admin.clients.approve', $client) }}">@csrf<button class="btn-primary">Approve</button></form>
        @endif
        @if($client->status->value !== 'suspended')
            <form method="POST" action="{{ route('admin.clients.suspend', $client) }}">@csrf<button class="btn-danger">Suspend</button></form>
        @endif
        <a href="{{ route('admin.clients.edit', $client) }}" class="btn-secondary">Edit</a>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="SMS balance" :value="number_format($client->sms_balance, 2)" />
        <x-stat label="Credit limit" :value="number_format($client->credit_limit, 2)" />
        <x-stat label="Price / SMS" :value="number_format($client->price_per_sms, 4)" />
        <x-stat label="Billing" :value="ucfirst($client->billing_type->value)" />
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section class="table-wrap p-5">
            <h2 class="font-semibold">Organisation</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Contact</dt><dd>{{ $client->contact_name }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Phone</dt><dd>{{ $client->contact_phone ?: '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Provider</dt><dd>{{ $client->defaultProvider?->name ?: '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Permissions</dt><dd>
                    {{ collect([
                        $client->can_send_otp ? 'OTP' : null,
                        $client->can_send_transactional ? 'Transactional' : null,
                        $client->can_send_marketing ? 'Marketing' : null,
                        $client->api_enabled ? 'API' : null,
                    ])->filter()->join(', ') ?: 'None' }}
                </dd></div>
            </dl>
        </section>

        <section class="table-wrap p-5">
            <h2 class="font-semibold">Users</h2>
            <div class="mt-3 space-y-2 text-sm">
                @forelse($client->users as $user)
                    <div class="flex justify-between border-b border-slate-100 py-2">
                        <span>{{ $user->name }} <span class="text-slate-500">({{ $user->email }})</span></span>
                        <span class="badge bg-slate-100">{{ $user->pivot->role }}</span>
                    </div>
                @empty
                    <p class="text-slate-500">No users linked.</p>
                @endforelse
            </div>
        </section>

        <section class="table-wrap p-5 lg:col-span-2">
            <h2 class="font-semibold">Sender IDs</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase text-slate-500"><tr><th class="py-2">Sender</th><th>Status</th><th>Category</th></tr></thead>
                    <tbody>
                    @foreach($client->senderIds as $sid)
                        <tr class="border-t border-slate-100">
                            <td class="py-2 font-medium">{{ $sid->sender }}</td>
                            <td>{{ $sid->status->value }}</td>
                            <td>{{ $sid->category?->value ?: 'any' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="table-wrap p-5 lg:col-span-2">
            <h2 class="font-semibold">Recent ledger</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase text-slate-500"><tr><th class="py-2">Ref</th><th>Type</th><th>Amount</th><th>Balance after</th><th>Reason</th></tr></thead>
                    <tbody>
                    @foreach($client->ledgerEntries as $entry)
                        <tr class="border-t border-slate-100">
                            <td class="py-2">{{ $entry->reference }}</td>
                            <td>{{ $entry->type->value }}</td>
                            <td>{{ number_format($entry->amount, 2) }}</td>
                            <td>{{ number_format($entry->balance_after, 2) }}</td>
                            <td>{{ $entry->reason }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-portal-layout>
