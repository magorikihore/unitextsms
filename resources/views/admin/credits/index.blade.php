<x-portal-layout title="Payments" portalLabel="Administration" header="Customer payments" subheader="All client top-ups — search by receipt, customer, cash, bank, or mobile" :nav="\App\Support\PortalNav::admin('credits')">
    <div class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="table-wrap px-4 py-3">
            <div class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Payments total</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums text-slate-900">{{ number_format($stats['total'], 2) }}</div>
            <div class="mt-0.5 text-xs text-slate-500">{{ number_format($stats['count']) }} payment{{ $stats['count'] === 1 ? '' : 's' }}</div>
        </div>
        <div class="table-wrap px-4 py-3">
            <div class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Cash</div>
            <div class="mt-1 text-xl font-semibold tabular-nums text-slate-800">{{ number_format($stats['cash'], 2) }}</div>
        </div>
        <div class="table-wrap px-4 py-3">
            <div class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Bank transfer</div>
            <div class="mt-1 text-xl font-semibold tabular-nums text-blue-800">{{ number_format($stats['bank'], 2) }}</div>
        </div>
        <div class="table-wrap px-4 py-3">
            <div class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Mobile money</div>
            <div class="mt-1 text-xl font-semibold tabular-nums text-teal-800">{{ number_format($stats['mobile'], 2) }}</div>
        </div>
    </div>

    <section class="table-wrap mb-4 p-3">
        <form method="POST" action="{{ route('admin.credits.store') }}" data-ajax class="toolbar-row" novalidate
              x-data="{ action: 'credit' }">
            @csrf
            <div data-form-error class="hidden w-full rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-sm text-red-700"></div>

            <x-client-search-select
                class="min-w-[16rem] max-w-md flex-[1.4]"
                name="client_id"
                :clients="$clients"
                :selected="old('client_id', request('client_id'))"
                :required="true"
                placeholder="Search client…"
            />

            <select name="action" class="input-field-sm w-auto min-w-[8.5rem]" x-model="action">
                <option value="credit">Add credit</option>
                <option value="debit">Remove credit</option>
            </select>

            <select name="payment_method" class="input-field-sm w-auto min-w-[9rem]"
                    x-bind:required="action === 'credit'"
                    x-bind:disabled="action !== 'credit'"
                    x-show="action === 'credit'">
                <option value="">Pay method…</option>
                <option value="cash">Cash</option>
                <option value="bank">Bank transfer</option>
            </select>

            <input type="text" name="receipt" class="input-field-sm w-36" placeholder="Receipt #" x-show="action === 'credit'" x-bind:disabled="action !== 'credit'">

            <input type="number" step="0.01" min="0.01" name="amount" class="input-field-sm w-28" placeholder="Amount" required>

            <input name="reason" class="input-field-sm min-w-[10rem] flex-1" placeholder="Reason / note" required>

            <button type="submit" data-loading-text="Saving…" class="btn-sm-primary shrink-0">Post entry</button>
        </form>
        <p class="mt-2 text-xs text-slate-500">Manual top-ups require <span class="font-medium">Cash</span> or <span class="font-medium">Bank</span>. Mobile money from PayIn is recorded automatically.</p>
    </section>

    <section class="table-wrap mb-4 overflow-hidden">
        <div class="border-b border-slate-100 px-3 py-2">
            <form method="GET" class="toolbar-row">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <input type="search" name="q" value="{{ $q }}" class="input-field-sm min-w-[14rem] flex-1" placeholder="Search receipt, reference, phone, customer…">
                <x-client-search-select
                    class="max-w-sm"
                    name="client_id"
                    :clients="$clients"
                    :selected="request('client_id')"
                    :allow-empty="true"
                    empty-label="All customers"
                    placeholder="Filter customer…"
                />
                <select name="payment_method" class="input-field-sm w-auto min-w-[9rem]">
                    <option value="">All methods</option>
                    @foreach($paymentMethods as $method)
                        <option value="{{ $method->value }}" @selected(request('payment_method') === $method->value)>{{ $method->label() }}</option>
                    @endforeach
                </select>
                @if($tab === 'all')
                    <select name="type" class="input-field-sm w-auto min-w-[7.5rem]">
                        <option value="">All types</option>
                        <option value="credit" @selected(request('type') === 'credit')>Credit</option>
                        <option value="debit" @selected(request('type') === 'debit')>Debit</option>
                        <option value="adjustment" @selected(request('type') === 'adjustment')>Adjustment</option>
                        <option value="refund" @selected(request('type') === 'refund')>Refund</option>
                    </select>
                @endif
                <button class="btn-sm-primary shrink-0">Search</button>
                @if(request()->hasAny(['client_id', 'payment_method', 'type', 'q']))
                    <a href="{{ route('admin.credits.index', ['tab' => $tab]) }}" class="btn-sm-secondary shrink-0">Clear</a>
                @endif
            </form>
        </div>
    </section>

    @if($deposits->isNotEmpty() && (request('payment_method') === null || request('payment_method') === '' || request('payment_method') === 'mobile'))
        <section class="table-wrap mb-4 overflow-hidden">
            <div class="border-b border-slate-100 px-4 py-3">
                <h2 class="text-sm font-semibold text-slate-900">Recent mobile deposits</h2>
                <p class="mt-0.5 text-xs text-slate-500">PayIn collection attempts (includes pending / failed)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">When</th>
                        <th class="px-3 py-2">Customer</th>
                        <th class="px-3 py-2">Phone</th>
                        <th class="px-3 py-2">Amount</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Receipt / ref</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach($deposits as $deposit)
                        <tr class="hover:bg-slate-50/70">
                            <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ $deposit->created_at->format('Y-m-d H:i') }}</td>
                            <td class="px-3 py-2">
                                @if($deposit->client)
                                    <a href="{{ route('admin.clients.show', $deposit->client) }}" class="font-medium text-blue-700 hover:underline">{{ $deposit->client->company_name }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 py-2">{{ $deposit->phone }}</td>
                            <td class="px-3 py-2 font-medium">{{ number_format((float) $deposit->amount, 2) }} {{ $deposit->currency }}</td>
                            <td class="px-3 py-2">
                                @php
                                    $statusClass = match ($deposit->status) {
                                        'completed' => 'bg-emerald-50 text-emerald-800',
                                        'failed', 'expired', 'rejected' => 'bg-red-50 text-red-700',
                                        default => 'bg-amber-50 text-amber-800',
                                    };
                                @endphp
                                <span class="badge {{ $statusClass }}">{{ $deposit->status }}</span>
                            </td>
                            <td class="px-3 py-2 font-mono text-xs text-slate-500">
                                {{ $deposit->receipt_number ?: ($deposit->request_ref ?: $deposit->reference) }}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="table-wrap overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">{{ $tab === 'payments' ? 'Customer payments' : 'Full ledger' }}</h2>
                <p class="mt-0.5 text-xs text-slate-500">
                    @if($tab === 'payments')
                        Credits bought via cash, bank, or mobile money
                    @else
                        All ledger movements including SMS usage debits
                    @endif
                </p>
            </div>
            <div class="flex gap-1 rounded-md border border-slate-200 bg-slate-50 p-0.5 text-xs font-medium">
                <a href="{{ route('admin.credits.index', array_filter(['tab' => 'payments', 'q' => $q ?: null, 'client_id' => request('client_id'), 'payment_method' => request('payment_method')])) }}"
                   data-ajax-nav
                   class="rounded px-2.5 py-1.5 {{ $tab === 'payments' ? 'bg-white text-blue-800 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                    Payments
                </a>
                <a href="{{ route('admin.credits.index', array_filter(['tab' => 'all', 'q' => $q ?: null, 'client_id' => request('client_id'), 'payment_method' => request('payment_method'), 'type' => request('type')])) }}"
                   data-ajax-nav
                   class="rounded px-2.5 py-1.5 {{ $tab === 'all' ? 'bg-white text-blue-800 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                    All activity
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2">When</th>
                    <th class="px-3 py-2">Customer</th>
                    <th class="px-3 py-2">Type</th>
                    <th class="px-3 py-2">Method</th>
                    <th class="px-3 py-2">Amount</th>
                    <th class="px-3 py-2">Before → After</th>
                    <th class="px-3 py-2">Details</th>
                    <th class="px-3 py-2">Receipt / ref</th>
                    <th class="px-3 py-2">By</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($entries as $entry)
                    @php
                        $receipt = $entry->meta['receipt_number']
                            ?? $entry->meta['request_ref']
                            ?? null;
                    @endphp
                    <tr class="hover:bg-slate-50/70">
                        <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ $entry->created_at->format('Y-m-d H:i') }}</td>
                        <td class="px-3 py-2">
                            @if($entry->client)
                                <a href="{{ route('admin.clients.show', $entry->client) }}" class="font-medium text-blue-700 hover:underline">{{ $entry->client->company_name }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            <span class="badge {{ $entry->type->value === 'credit' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800' }}">{{ $entry->type->value }}</span>
                        </td>
                        <td class="px-3 py-2">
                            @if($entry->payment_method)
                                <span class="badge
                                    {{ $entry->payment_method === \App\Enums\PaymentMethod::Mobile ? 'bg-teal-50 text-teal-800' : ($entry->payment_method === \App\Enums\PaymentMethod::Cash ? 'bg-slate-100 text-slate-700' : 'bg-blue-50 text-blue-800') }}
                                ">{{ $entry->payment_method->label() }}</span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 font-medium tabular-nums {{ $entry->type->value === 'credit' ? 'text-emerald-800' : '' }}">
                            {{ $entry->type->value === 'credit' ? '+' : '−' }}{{ number_format((float) $entry->amount, 2) }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ number_format((float) $entry->balance_before, 2) }} → {{ number_format((float) $entry->balance_after, 2) }}</td>
                        <td class="px-3 py-2 text-slate-600">{{ $entry->reason }}</td>
                        <td class="px-3 py-2 font-mono text-xs text-slate-500">
                            <div>{{ $entry->reference }}</div>
                            @if($receipt)
                                <div class="text-slate-400">{{ $receipt }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-slate-500">{{ $entry->creator?->name ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-3 py-8 text-center text-slate-500">No payments match this search.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <div class="mt-3">{{ $entries->links() }}</div>
</x-portal-layout>
