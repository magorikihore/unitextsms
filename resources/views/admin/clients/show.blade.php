<x-portal-layout title="{{ $client->company_name }}" portalLabel="Administration" header="{{ $client->company_name }}" subheader="{{ $client->contact_email }} · {{ $client->status->value }} · balance {{ number_format($client->sms_balance, 2) }}" :nav="\App\Support\PortalNav::admin('clients')">
    <div class="mb-6 toolbar-row">
        <a href="{{ route('admin.clients.edit', $client) }}" class="btn-sm-primary">Update client</a>
        @if($client->status !== \App\Enums\ClientStatus::Active)
            <form method="POST" action="{{ route('admin.clients.activate', $client) }}">@csrf<button class="btn-sm-secondary">Activate</button></form>
        @endif
        @if($client->status === \App\Enums\ClientStatus::Pending)
            <form method="POST" action="{{ route('admin.clients.approve', $client) }}">@csrf<button class="btn-sm-secondary">Approve</button></form>
        @endif
        @if($client->status !== \App\Enums\ClientStatus::Suspended)
            <form method="POST" action="{{ route('admin.clients.suspend', $client) }}">@csrf<button class="btn-sm-secondary text-red-700">Deactivate</button></form>
        @endif
        <a href="{{ route('admin.credits.index', ['client_id' => $client->id]) }}" class="btn-sm-secondary">Full ledger</a>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="SMS balance" :value="number_format($client->sms_balance, 2)" tone="ok" />
        <x-stat label="Credit limit" :value="number_format($client->credit_limit, 2)" />
        <x-stat label="Price / SMS" :value="number_format($client->price_per_sms, 4)" />
        <x-stat label="Billing" :value="ucfirst($client->billing_type->value)" />
    </div>

    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        <section class="table-wrap p-5">
            <h2 class="font-semibold">Add / remove credit</h2>
            <p class="mt-1 text-sm text-slate-500">Writes an immutable ledger entry and updates balance.</p>
            <form method="POST" action="{{ route('admin.clients.credits', $client) }}" data-ajax class="mt-4 grid gap-2 sm:grid-cols-2" novalidate
                  x-data="{ action: 'credit' }">
                @csrf
                <div data-form-error class="hidden sm:col-span-2 rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-sm text-red-700"></div>
                <select name="action" class="input-field-sm" required x-model="action">
                    <option value="credit">Add credit</option>
                    <option value="debit">Remove credit</option>
                </select>
                <input type="number" step="0.01" min="0.01" name="amount" class="input-field-sm" placeholder="Amount" required>
                <select name="payment_method" class="input-field-sm sm:col-span-2"
                        x-show="action === 'credit'"
                        x-bind:required="action === 'credit'"
                        x-bind:disabled="action !== 'credit'">
                    <option value="">How was this paid?</option>
                    <option value="cash">Cash</option>
                    <option value="bank">Bank transfer</option>
                </select>
                <input name="reason" class="input-field-sm sm:col-span-2" placeholder="Reason (shown on ledger)" required>
                <button type="submit" data-loading-text="Saving…" class="btn-sm-primary sm:col-span-2">Post to ledger</button>
            </form>
        </section>

        <section class="table-wrap p-5">
            <h2 class="font-semibold">Organisation</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Contact</dt><dd>{{ $client->contact_name }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Phone</dt><dd>{{ $client->contact_phone ?: '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Provider</dt><dd>{{ $client->defaultProvider?->name ?: '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">API</dt><dd>{{ $client->api_enabled ? 'Enabled' : 'Disabled' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Permissions</dt><dd>
                    {{ collect([
                        $client->can_send_otp ? 'OTP' : null,
                        $client->can_send_transactional ? 'Transactional' : null,
                        $client->can_send_marketing ? 'Bulk SMS' : null,
                    ])->filter()->join(', ') ?: 'None' }}
                </dd></div>
            </dl>
        </section>
    </div>

    <section class="table-wrap mb-6 p-5">
        <h2 class="font-semibold">Users — reset password / deactivate</h2>
        <div class="mt-4 space-y-4">
            @forelse($client->users as $user)
                <div class="rounded-lg border border-slate-100 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div class="font-medium">{{ $user->name }}</div>
                            <div class="text-sm text-slate-500">{{ $user->email }} · {{ $user->pivot->role }} · {{ $user->pivot->is_active ? 'active' : 'inactive' }}</div>
                        </div>
                        <form method="POST" action="{{ route('admin.clients.users.toggle', [$client, $user]) }}">
                            @csrf
                            <button class="btn-sm-secondary">{{ $user->pivot->is_active ? 'Deactivate' : 'Activate' }}</button>
                        </form>
                    </div>
                    @unless($user->isPlatformUser())
                        <form method="POST" action="{{ route('admin.clients.users.reset-password', [$client, $user]) }}" data-ajax class="mt-3 toolbar-row" novalidate>
                            @csrf
                            <div data-form-error class="hidden w-full rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-sm text-red-700"></div>
                            <x-password-input name="password" size="sm" class="min-w-[8rem] flex-1" placeholder="New password" required autocomplete="new-password" />
                            <x-password-input name="password_confirmation" size="sm" class="min-w-[8rem] flex-1" placeholder="Confirm" required autocomplete="new-password" />
                            <button type="submit" data-loading-text="Resetting…" class="btn-sm-primary">Reset password</button>
                        </form>
                    @endunless
                </div>
            @empty
                <p class="text-sm text-slate-500">No users linked.</p>
            @endforelse
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="table-wrap p-5">
            <h2 class="font-semibold">Sender IDs</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase text-slate-500"><tr><th class="py-2">Sender</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse($client->senderIds as $sid)
                        <tr class="border-t border-slate-100">
                            <td class="py-2 font-medium">{{ $sid->sender }}</td>
                            <td>{{ $sid->status->value }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="py-3 text-slate-500">No sender IDs.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="table-wrap p-5">
            <h2 class="font-semibold">Recent ledger</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase text-slate-500"><tr><th class="py-2">Ref</th><th>Type</th><th>Method</th><th>Amount</th><th>After</th><th>Reason</th></tr></thead>
                    <tbody>
                    @forelse($client->ledgerEntries as $entry)
                        <tr class="border-t border-slate-100">
                            <td class="py-2 font-mono text-xs">{{ $entry->reference }}</td>
                            <td>{{ $entry->type->value }}</td>
                            <td>{{ $entry->payment_method?->label() ?: '—' }}</td>
                            <td>{{ number_format($entry->amount, 2) }}</td>
                            <td>{{ number_format($entry->balance_after, 2) }}</td>
                            <td>{{ $entry->reason }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-3 text-slate-500">No ledger entries yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-portal-layout>
