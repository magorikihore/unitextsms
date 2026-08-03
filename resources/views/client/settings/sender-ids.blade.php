@php
    $senderRows = $senderIds->map(fn ($sid) => [
        'sender' => $sid->sender,
        'status' => $sid->status->value,
        'expires' => $sid->expires_at?->toDateString() ?: '—',
        'search' => strtolower($sid->sender.' '.$sid->status->value),
    ])->values();
@endphp

<x-portal-layout title="Sender IDs" portalLabel="{{ $client->company_name }}" header="Sender IDs" subheader="Search your sender IDs or request a new one" :nav="\App\Support\PortalNav::client($client, 'sender-ids')">
    <div
        class="space-y-4"
        x-data="{
            open: false,
            q: '',
            rows: @js($senderRows),
            get filtered() {
                const term = (this.q || '').trim().toLowerCase();
                if (!term) return this.rows;
                return this.rows.filter((r) => (r.search || '').includes(term));
            }
        }"
        @keydown.escape.window="open = false"
    >
        <div class="table-wrap p-3">
            <div class="toolbar-row">
                <input type="search"
                       x-model="q"
                       class="input-field-sm max-w-xs flex-1"
                       placeholder="Search sender ID…">
                <button type="button" class="btn-sm-primary" @click="open = true">Request sender ID</button>
            </div>
        </div>

        <div class="table-wrap overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Sender</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Expires</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    <template x-for="row in filtered" :key="row.sender + row.status">
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-3 py-2 font-semibold text-slate-900" x-text="row.sender"></td>
                            <td class="px-3 py-2 capitalize text-slate-600" x-text="row.status"></td>
                            <td class="px-3 py-2 text-slate-600" x-text="row.expires"></td>
                        </tr>
                    </template>
                    </tbody>
                </table>
            </div>
            <div class="px-3 py-8 text-center text-sm text-slate-500" x-show="filtered.length === 0">
                <span x-show="!q.trim()">No sender IDs yet. Request one to get started.</span>
                <span x-show="q.trim()">No sender IDs match your search.</span>
            </div>
        </div>

        <template x-teleport="body">
            <div x-show="open" x-cloak
                 class="fixed inset-0 z-[200] flex items-center justify-center overflow-y-auto bg-slate-900/45 p-4"
                 role="dialog" aria-modal="true">
                <div class="absolute inset-0" @click="open = false"></div>
                <div class="relative z-10 my-auto w-full max-w-sm rounded-xl border border-slate-200 bg-white shadow-2xl" @click.stop>
                    <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                        <div class="text-base font-semibold text-slate-900">Request sender ID</div>
                        <button type="button" class="text-2xl leading-none text-slate-400 hover:text-slate-700" @click="open = false" aria-label="Close">&times;</button>
                    </div>
                    <form method="POST" action="{{ route('client.sender-ids.store', $client) }}" data-ajax class="space-y-3 px-4 py-4" novalidate>
                        @csrf
                        <div data-form-error class="hidden rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-sm text-red-700"></div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Sender ID</label>
                            <input name="sender" class="input-field-sm w-full uppercase" maxlength="11" placeholder="e.g. MYBRAND" autocomplete="off">
                            <p class="field-error hidden" data-error-for="sender"></p>
                            <p class="mt-1 text-xs text-slate-500">Max 11 letters/numbers. Works for Single and Bulk SMS once approved.</p>
                        </div>
                        <div class="flex justify-end gap-2 pt-1">
                            <button type="button" class="btn-sm-secondary" @click="open = false">Cancel</button>
                            <button type="submit" class="btn-sm-primary" data-loading-text="Submitting…">Submit request</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </div>
</x-portal-layout>
