@props([
    'name' => 'client_id',
    'clients' => [],
    'selected' => null,
    'required' => false,
    'placeholder' => 'Search client…',
    'allowEmpty' => false,
    'emptyLabel' => 'All clients',
    'compact' => true,
])

@php
    $options = collect($clients)->map(fn ($c) => [
        'id' => (int) $c->id,
        'label' => $c->company_name,
        'email' => $c->contact_email ?? '',
        'balance' => number_format((float) ($c->sms_balance ?? 0), 2),
        'search' => strtolower(($c->company_name ?? '').' '.($c->contact_email ?? '').' '.($c->contact_phone ?? '')),
    ])->values();

    $selectedClient = $options->firstWhere('id', (int) $selected);
    $initialLabel = $selectedClient
        ? $selectedClient['label'].' · '.$selectedClient['balance']
        : ($allowEmpty && ! $selected ? $emptyLabel : '');
    $inputClass = $compact ? 'input-field-sm' : 'input-field';
@endphp

<div
    {{ $attributes->class(['relative min-w-[14rem] flex-1']) }}
    x-data="{
        open: false,
        q: @js($initialLabel),
        selectedId: @js($selected ? (int) $selected : ''),
        selectedLabel: @js($initialLabel),
        options: @js($options),
        allowEmpty: @js((bool) $allowEmpty),
        emptyLabel: @js($emptyLabel),
        get filtered() {
            const term = (this.q || '').trim().toLowerCase();
            // If the box still shows the selected label, show a useful short list.
            if (this.selectedId && this.q === this.selectedLabel) {
                return this.options.slice(0, 40);
            }
            if (!term || term === this.emptyLabel.toLowerCase()) {
                return this.options.slice(0, 40);
            }
            return this.options.filter(o => o.search.includes(term)).slice(0, 40);
        },
        openList() {
            this.open = true;
            if (this.selectedId && this.q === this.selectedLabel) {
                this.q = '';
            }
            if (!this.selectedId && this.q === this.emptyLabel) {
                this.q = '';
            }
        },
        choose(option) {
            this.selectedId = option.id;
            this.selectedLabel = option.label + ' · ' + option.balance;
            this.q = this.selectedLabel;
            this.open = false;
        },
        clearAll() {
            this.selectedId = '';
            this.selectedLabel = this.allowEmpty ? this.emptyLabel : '';
            this.q = this.allowEmpty ? this.emptyLabel : '';
            this.open = false;
        },
        onBlur() {
            // Keep dropdown usable; close shortly after focus leaves the widget.
            setTimeout(() => {
                if (! this.$el.contains(document.activeElement)) {
                    this.open = false;
                    if (this.selectedId) {
                        this.q = this.selectedLabel;
                    } else if (this.allowEmpty) {
                        this.q = this.emptyLabel;
                    } else {
                        this.q = '';
                    }
                }
            }, 120);
        }
    }"
    @keydown.escape.window="open = false"
>
    <input type="hidden" name="{{ $name }}" x-model="selectedId" @if($required) :required="!selectedId" @endif>

    <div class="relative">
        <input
            type="text"
            class="{{ $inputClass }} w-full pe-8"
            x-model="q"
            @focus="openList()"
            @click="openList()"
            @input="open = true"
            @blur="onBlur()"
            @keydown.arrow-down.prevent="open = true"
            @keydown.enter.prevent="if (filtered.length) choose(filtered[0])"
            placeholder="{{ $placeholder }}"
            autocomplete="off"
            role="combobox"
            aria-autocomplete="list"
            :aria-expanded="open.toString()"
        >
        <button type="button"
                class="absolute inset-y-0 end-0 flex w-8 items-center justify-center text-slate-400 hover:text-slate-600"
                tabindex="-1"
                @mousedown.prevent
                @click="open ? (open = false) : openList()">
            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
        </button>
    </div>

    <div x-show="open"
         x-cloak
         class="absolute left-0 right-0 z-40 mt-1 overflow-hidden rounded-md border border-slate-200 bg-white shadow-lg"
         @mousedown.prevent>
        <ul class="max-h-56 overflow-y-auto py-1 text-sm">
            @if($allowEmpty)
                <li>
                    <button type="button"
                            class="block w-full px-3 py-1.5 text-left text-slate-600 hover:bg-slate-50"
                            @mousedown.prevent="clearAll()">
                        {{ $emptyLabel }}
                    </button>
                </li>
            @endif
            <template x-for="option in filtered" :key="option.id">
                <li>
                    <button type="button"
                            class="block w-full px-3 py-1.5 text-left hover:bg-blue-50"
                            :class="selectedId === option.id ? 'bg-blue-50 text-blue-900' : ''"
                            @mousedown.prevent="choose(option)">
                        <div class="flex items-center justify-between gap-3">
                            <span class="truncate font-medium" x-text="option.label"></span>
                            <span class="shrink-0 text-xs text-slate-500" x-text="option.balance"></span>
                        </div>
                        <div class="truncate text-xs text-slate-500" x-text="option.email"></div>
                    </button>
                </li>
            </template>
            <li x-show="filtered.length === 0" class="px-3 py-2 text-xs text-slate-500">No clients match.</li>
        </ul>
    </div>
</div>
