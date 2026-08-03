<x-portal-layout
    title="Payments"
    portalLabel="{{ $client->company_name }}"
    header="Payments & credits"
    subheader="Your top-ups — search by receipt and filter cash, bank, or mobile"
    :nav="\App\Support\PortalNav::client($client, 'billing')"
>
    <div class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="table-wrap px-4 py-3">
            <div class="text-[10px] font-medium uppercase tracking-wide text-slate-500">SMS balance</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums text-teal-800">{{ number_format((float) $client->sms_balance, 2) }}</div>
        </div>
        <div class="table-wrap px-4 py-3">
            <div class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Credits bought</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums text-slate-900">{{ number_format($stats['total'], 2) }}</div>
            <div class="mt-0.5 text-xs text-slate-500">{{ $stats['count'] }} payment{{ $stats['count'] === 1 ? '' : 's' }}{{ request()->hasAny(['q', 'payment_method']) ? ' (filtered)' : '' }}</div>
        </div>
        <div class="table-wrap px-4 py-3">
            <div class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Cash · Bank</div>
            <div class="mt-1 text-lg font-semibold tabular-nums text-slate-800">{{ number_format($stats['cash'], 2) }} <span class="text-slate-400">·</span> {{ number_format($stats['bank'], 2) }}</div>
        </div>
        <div class="table-wrap px-4 py-3">
            <div class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Mobile money</div>
            <div class="mt-1 text-xl font-semibold tabular-nums text-teal-800">{{ number_format($stats['mobile'], 2) }}</div>
            <div class="mt-0.5 text-xs text-slate-500">Deposit in the header to top up</div>
        </div>
    </div>

    <section class="table-wrap mb-4 overflow-hidden">
        <div class="border-b border-slate-100 px-3 py-2">
            <form method="GET" class="toolbar-row">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <input type="search" name="q" value="{{ $q }}" class="input-field-sm min-w-[14rem] flex-1" placeholder="Search receipt, reference, phone…">
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
                @if(request()->hasAny(['q', 'payment_method', 'type']))
                    <a href="{{ route('client.billing.index', [$client, 'tab' => $tab]) }}" data-ajax-nav class="btn-sm-secondary shrink-0">Clear</a>
                @endif
            </form>
        </div>
    </section>

    @if($deposits->isNotEmpty() && (request('payment_method') === null || request('payment_method') === '' || request('payment_method') === 'mobile'))
        <section class="table-wrap mb-5 overflow-hidden">
            <div class="border-b border-slate-100 px-4 py-3">
                <h2 class="text-sm font-semibold text-slate-900">Recent mobile payments</h2>
                <p class="mt-0.5 text-xs text-slate-500">PayIn deposits from your Deposit button</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2">When</th>
                        <th class="px-4 py-2">Phone</th>
                        <th class="px-4 py-2">Amount</th>
                        <th class="px-4 py-2">Credits</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2">Receipt / ref</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach($deposits as $deposit)
                        <tr>
                            <td class="whitespace-nowrap px-4 py-2.5 text-slate-600">{{ $deposit->created_at->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-2.5">{{ $deposit->phone }}</td>
                            <td class="px-4 py-2.5 font-medium">{{ number_format((float) $deposit->amount, 2) }} {{ $deposit->currency }}</td>
                            <td class="px-4 py-2.5">{{ $deposit->credits !== null ? number_format((float) $deposit->credits, 2) : '—' }}</td>
                            <td class="px-4 py-2.5">
                                @php
                                    $statusClass = match ($deposit->status) {
                                        'completed' => 'bg-emerald-50 text-emerald-800',
                                        'failed', 'expired', 'rejected' => 'bg-red-50 text-red-700',
                                        default => 'bg-amber-50 text-amber-800',
                                    };
                                @endphp
                                <span class="badge {{ $statusClass }}">{{ $deposit->status }}</span>
                            </td>
                            <td class="px-4 py-2.5 font-mono text-xs text-slate-500">
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
                <h2 class="text-sm font-semibold text-slate-900">Transaction history</h2>
                <p class="mt-0.5 text-xs text-slate-500">Payments you made and credits added to your account</p>
            </div>
            <div class="flex gap-1 rounded-md border border-slate-200 bg-slate-50 p-0.5 text-xs font-medium">
                <a href="{{ route('client.billing.index', array_filter([$client, 'tab' => 'purchases', 'q' => $q ?: null, 'payment_method' => request('payment_method')])) }}"
                   data-ajax-nav
                   class="rounded px-2.5 py-1.5 {{ $tab === 'purchases' ? 'bg-white text-teal-800 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                    Credits bought
                </a>
                <a href="{{ route('client.billing.index', array_filter([$client, 'tab' => 'all', 'q' => $q ?: null, 'payment_method' => request('payment_method'), 'type' => request('type')])) }}"
                   data-ajax-nav
                   class="rounded px-2.5 py-1.5 {{ $tab === 'all' ? 'bg-white text-teal-800 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                    All activity
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2">When</th>
                    <th class="px-4 py-2">Type</th>
                    <th class="px-4 py-2">Method</th>
                    <th class="px-4 py-2">Amount</th>
                    <th class="px-4 py-2">Balance after</th>
                    <th class="px-4 py-2">Details</th>
                    <th class="px-4 py-2">Receipt / ref</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($entries as $entry)
                    @php
                        $receipt = $entry->meta['receipt_number'] ?? $entry->meta['request_ref'] ?? null;
                    @endphp
                    <tr class="hover:bg-slate-50/70">
                        <td class="whitespace-nowrap px-4 py-2.5 text-slate-600">{{ $entry->created_at->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-2.5">
                            <span class="badge {{ $entry->type->value === 'credit' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800' }}">
                                {{ $entry->type->value }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5">
                            @if($entry->payment_method)
                                <span class="badge
                                    {{ $entry->payment_method === \App\Enums\PaymentMethod::Mobile ? 'bg-teal-50 text-teal-800' : ($entry->payment_method === \App\Enums\PaymentMethod::Cash ? 'bg-slate-100 text-slate-700' : 'bg-blue-50 text-blue-800') }}
                                ">{{ $entry->payment_method->label() }}</span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 font-medium tabular-nums {{ $entry->type->value === 'credit' ? 'text-emerald-800' : 'text-slate-800' }}">
                            {{ $entry->type->value === 'credit' ? '+' : '−' }}{{ number_format((float) $entry->amount, 2) }}
                        </td>
                        <td class="px-4 py-2.5 tabular-nums text-slate-600">{{ number_format((float) $entry->balance_after, 2) }}</td>
                        <td class="px-4 py-2.5 text-slate-600">{{ $entry->reason }}</td>
                        <td class="px-4 py-2.5 font-mono text-xs text-slate-500">
                            <div>{{ $entry->reference }}</div>
                            @if($receipt)
                                <div class="text-slate-400">{{ $receipt }}</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-slate-500">
                            @if(request()->hasAny(['q', 'payment_method', 'type']))
                                No payments match this search.
                            @elseif($tab === 'purchases')
                                No payments yet. Use <span class="font-medium text-slate-700">Deposit</span> in the header to top up with mobile money.
                            @else
                                No account activity yet.
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="mt-3">{{ $entries->links() }}</div>
</x-portal-layout>
