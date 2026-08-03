@php
    $templatesPayload = $templates->map(fn ($t) => [
        'id' => $t->id,
        'name' => $t->name,
        'language' => $t->language,
        'body' => $t->body,
    ])->values();

    $contactsPayload = $contacts->map(fn ($c) => [
        'id' => $c->id,
        'phone' => $c->phone_normalized,
        'name' => trim(($c->first_name.' '.$c->last_name)) ?: $c->phone_normalized,
        'search' => strtolower(trim(($c->first_name.' '.$c->last_name.' '.$c->phone_normalized))),
    ])->values();
@endphp

<x-portal-layout title="Send SMS" portalLabel="{{ $client->company_name }}" header="Send SMS" subheader="Choose Single or Bulk SMS, then compose and send" :nav="\App\Support\PortalNav::client($client, 'send')">
    <div
        class="mx-auto max-w-3xl"
        x-data="sendSmsFlow({
            previewUrl: @js(route('client.send.preview', $client)),
            storeUrl: @js(route('client.send.store', $client)),
            csrf: @js(csrf_token()),
            templates: @js($templatesPayload),
            contacts: @js($contactsPayload),
            balance: {{ (float) $client->sms_balance }},
            defaultSenderId: @js($defaultSenderId ?? ''),
        })"
    >
        {{-- Mode picker --}}
        <div class="mb-5 grid gap-3 sm:grid-cols-2">
            <button type="button"
                    class="send-mode-card send-mode-card-single"
                    :class="mode === 'single' && 'is-selected'"
                    @click="setMode('single')">
                <div class="send-mode-card-title">Single SMS</div>
                <p class="send-mode-card-desc">One recipient — enter a number or pick a saved contact.</p>
            </button>
            <button type="button"
                    class="send-mode-card send-mode-card-bulk"
                    :class="mode === 'bulk' && 'is-selected'"
                    @click="setMode('bulk')">
                <div class="send-mode-card-title">Bulk SMS</div>
                <p class="send-mode-card-desc">Many recipients — paste numbers, upload a file, or select saved contacts.</p>
            </button>
        </div>

        {{-- Progress --}}
        <ol class="mb-5 flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-slate-400">
            <li class="flex items-center gap-2" :class="step === 'compose' ? 'text-teal-700' : 'text-slate-500'">
                <span class="flex h-6 w-6 items-center justify-center rounded-full border text-[11px]"
                      :class="step !== 'compose' ? 'border-teal-600 bg-teal-600 text-white' : 'border-teal-600 text-teal-700'">1</span>
                Compose
            </li>
            <li class="h-px flex-1 bg-slate-200"></li>
            <li class="flex items-center gap-2" :class="step === 'review' ? 'text-teal-700' : (step === 'done' ? 'text-slate-500' : '')">
                <span class="flex h-6 w-6 items-center justify-center rounded-full border text-[11px]"
                      :class="step === 'done' ? 'border-teal-600 bg-teal-600 text-white' : (step === 'review' ? 'border-teal-600 text-teal-700' : 'border-slate-300')">2</span>
                Review
            </li>
            <li class="h-px flex-1 bg-slate-200"></li>
            <li class="flex items-center gap-2" :class="step === 'done' ? 'text-teal-700' : ''">
                <span class="flex h-6 w-6 items-center justify-center rounded-full border text-[11px]"
                      :class="step === 'done' ? 'border-teal-600 bg-teal-600 text-white' : 'border-slate-300'">3</span>
                Result
            </li>
        </ol>

        {{-- Compose --}}
        <form x-show="step === 'compose'" x-cloak @submit.prevent="preview()" class="table-wrap space-y-4 p-5" novalidate>
            <div x-show="formError" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" x-text="formError"></div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="text-sm font-medium text-slate-700">
                        <span x-text="mode === 'bulk' ? 'Campaign name' : 'Message name'"></span>
                    </label>
                    <input name="name" x-model="form.name" class="input-field-sm mt-1" :class="errors.name && 'is-invalid'" placeholder="e.g. April OTP" autocomplete="off">
                    <p class="field-error" x-show="errors.name" x-text="errors.name"></p>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Approved sender ID</label>
                    <select name="sender_id_id" x-model="form.sender_id_id" class="input-field-sm mt-1" :class="errors.sender_id_id && 'is-invalid'">
                        <option value="">Select sender</option>
                        @foreach($senderIds as $sid)
                            <option value="{{ $sid->id }}">{{ $sid->sender }}</option>
                        @endforeach
                    </select>
                    <p class="field-error" x-show="errors.sender_id_id" x-text="errors.sender_id_id"></p>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Template <span class="font-normal text-slate-400">(optional)</span></label>
                    <select class="input-field-sm mt-1" x-model="form.template_id" @change="applyTemplate()">
                        <option value="">None</option>
                        <template x-for="tpl in templates" :key="tpl.id">
                            <option :value="String(tpl.id)" x-text="`${tpl.name} (${tpl.language})`"></option>
                        </template>
                    </select>
                </div>
                <div x-show="mode === 'bulk'">
                    <label class="text-sm font-medium text-slate-700">Schedule <span class="font-normal text-slate-400">(optional)</span></label>
                    <input type="datetime-local" name="scheduled_at" x-model="form.scheduled_at" class="input-field-sm mt-1" :class="errors.scheduled_at && 'is-invalid'">
                    <p class="field-error" x-show="errors.scheduled_at" x-text="errors.scheduled_at"></p>
                </div>
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">Message</label>
                <textarea name="message_body" x-model="form.message_body" rows="5" class="input-field-sm mt-1" :class="errors.message_body && 'is-invalid'" placeholder="Hello @{{first_name}}, your code is …"></textarea>
                <div class="mt-1 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                    <span>Variables: @{{first_name}}, @{{last_name}}, custom fields</span>
                    <span x-text="`${form.message_body.length} / 1000`"></span>
                </div>
                <p class="field-error" x-show="errors.message_body" x-text="errors.message_body"></p>
            </div>

            {{-- Recipients --}}
            <div class="space-y-3 rounded-lg border border-slate-100 bg-slate-50/60 p-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <label class="text-sm font-medium text-slate-700">Recipients</label>
                    <span class="text-xs text-slate-500" x-text="recipientHint()"></span>
                </div>

                <div x-show="mode === 'single'">
                    <input type="tel" x-model="form.numbers" class="input-field-sm font-mono text-[13px]" :class="errors.numbers && 'is-invalid'" placeholder="07XXXXXXXX" autocomplete="tel">
                    <p class="mt-1 text-xs text-slate-500">Or pick one saved contact below.</p>
                </div>

                <div x-show="mode === 'bulk'">
                    <textarea name="numbers" x-model="form.numbers" rows="5" class="input-field-sm font-mono text-[13px]" :class="errors.numbers && 'is-invalid'" placeholder="0758460813&#10;255754000111"></textarea>
                    <p class="mt-1 text-xs text-slate-500">One number per line, and/or select contacts, and/or upload a file.</p>
                </div>
                <p class="field-error" x-show="errors.numbers" x-text="errors.numbers"></p>

                <div>
                    <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                        <span class="text-xs font-medium uppercase tracking-wide text-slate-500">Saved contacts</span>
                        <div class="flex items-center gap-2">
                            <input type="search" x-model="contactQuery" class="input-field-sm w-44" placeholder="Search contacts…">
                            <button type="button" class="btn-sm-secondary" x-show="mode === 'bulk'" @click="selectAllFilteredContacts()">Select shown</button>
                            <button type="button" class="btn-sm-secondary" x-show="selectedContactIds.length" @click="clearContacts()">Clear</button>
                        </div>
                    </div>
                    <div class="max-h-48 overflow-y-auto rounded-md border border-slate-200 bg-white">
                        <template x-if="!filteredContacts.length">
                            <div class="px-3 py-6 text-center text-sm text-slate-500">
                                <span x-show="!contacts.length">No saved contacts yet. Add some under Contacts.</span>
                                <span x-show="contacts.length">No contacts match this search.</span>
                            </div>
                        </template>
                        <template x-for="c in filteredContacts" :key="c.id">
                            <label class="flex cursor-pointer items-center gap-2 border-b border-slate-50 px-3 py-2 text-sm hover:bg-slate-50">
                                <input type="checkbox"
                                       class="rounded border-slate-300 text-teal-700 focus:ring-teal-600"
                                       :value="c.id"
                                       :checked="selectedContactIds.includes(c.id)"
                                       @change="toggleContact(c.id)">
                                <span class="min-w-0 flex-1 truncate font-medium text-slate-800" x-text="c.name"></span>
                                <span class="font-mono text-xs text-slate-500" x-text="c.phone"></span>
                            </label>
                        </template>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        <span x-text="selectedContactIds.length"></span> contact<span x-show="selectedContactIds.length !== 1">s</span> selected
                    </p>
                </div>

                <div x-show="mode === 'bulk'">
                    <label class="text-sm font-medium text-slate-700">Upload CSV / Excel <span class="font-normal text-slate-400">(optional)</span></label>
                    <input type="file" name="file" accept=".csv,.xlsx,.xls,.txt" class="mt-1 block w-full text-sm text-slate-600" @change="onFile($event)" :class="errors.file && 'is-invalid'">
                    <p class="field-error" x-show="errors.file" x-text="errors.file"></p>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                <p class="text-sm text-slate-500">Balance <span class="font-semibold text-slate-800" x-text="formatMoney(balance)"></span> SMS units</p>
                <button type="submit" class="btn-sm-primary min-w-[9rem]" :disabled="busy" data-loading-text="Checking…">
                    <template x-if="busy && busyAction === 'preview'">
                        <span class="inline-flex items-center"><span class="btn-spinner"></span> Checking…</span>
                    </template>
                    <template x-if="!(busy && busyAction === 'preview')">
                        <span>Continue to review</span>
                    </template>
                </button>
            </div>
        </form>

        {{-- Review --}}
        <div x-show="step === 'review'" x-cloak class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="table-wrap p-3">
                    <div class="text-xs uppercase tracking-wide text-slate-500">Valid</div>
                    <div class="mt-1 text-2xl font-semibold text-emerald-700" x-text="preview.valid_count"></div>
                </div>
                <div class="table-wrap p-3">
                    <div class="text-xs uppercase tracking-wide text-slate-500">Invalid</div>
                    <div class="mt-1 text-2xl font-semibold text-red-600" x-text="preview.invalid_count"></div>
                </div>
                <div class="table-wrap p-3">
                    <div class="text-xs uppercase tracking-wide text-slate-500">Duplicates</div>
                    <div class="mt-1 text-2xl font-semibold text-slate-800" x-text="preview.duplicates_removed"></div>
                </div>
                <div class="table-wrap p-3">
                    <div class="text-xs uppercase tracking-wide text-slate-500">Opted out</div>
                    <div class="mt-1 text-2xl font-semibold text-amber-700" x-text="preview.opted_out_count"></div>
                </div>
                <div class="table-wrap p-3">
                    <div class="text-xs uppercase tracking-wide text-slate-500">Est. units</div>
                    <div class="mt-1 text-2xl font-semibold text-slate-800" x-text="preview.estimated_units"></div>
                </div>
                <div class="table-wrap p-3">
                    <div class="text-xs uppercase tracking-wide text-slate-500">Est. cost</div>
                    <div class="mt-1 text-2xl font-semibold text-slate-800" x-text="formatMoney(preview.estimated_cost)"></div>
                </div>
                <div class="table-wrap p-3">
                    <div class="text-xs uppercase tracking-wide text-slate-500">After send</div>
                    <div class="mt-1 text-2xl font-semibold" :class="preview.remaining_balance < 0 ? 'text-red-600' : 'text-emerald-700'" x-text="formatMoney(preview.remaining_balance)"></div>
                </div>
                <div class="table-wrap p-3">
                    <div class="text-xs uppercase tracking-wide text-slate-500">Est. time</div>
                    <div class="mt-1 text-2xl font-semibold text-slate-800"><span x-text="preview.estimated_completion_seconds"></span><span class="text-base font-normal text-slate-500">s</span></div>
                </div>
            </div>

            <div class="table-wrap p-5">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Message</div>
                <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-700" x-text="form.message_body"></p>
                <dl class="mt-4 grid gap-2 border-t border-slate-100 pt-3 text-sm sm:grid-cols-2">
                    <div class="flex justify-between gap-3 sm:block"><dt class="text-slate-500">Name</dt><dd class="font-medium" x-text="form.name"></dd></div>
                    <div class="flex justify-between gap-3 sm:block"><dt class="text-slate-500">Mode</dt><dd class="font-medium" x-text="mode === 'bulk' ? 'Bulk SMS' : 'Single SMS'"></dd></div>
                </dl>
            </div>

            <div x-show="preview.invalid_sample?.length || preview.opted_out_sample?.length" class="table-wrap p-4 text-sm">
                <template x-if="preview.invalid_sample?.length">
                    <div class="mb-2">
                        <div class="font-medium text-red-700">Invalid samples</div>
                        <p class="mt-1 font-mono text-xs text-slate-600" x-text="preview.invalid_sample.join(', ')"></p>
                    </div>
                </template>
                <template x-if="preview.opted_out_sample?.length">
                    <div>
                        <div class="font-medium text-amber-700">Opted out samples</div>
                        <p class="mt-1 font-mono text-xs text-slate-600" x-text="preview.opted_out_sample.join(', ')"></p>
                    </div>
                </template>
            </div>

            <div x-show="formError" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" x-text="formError"></div>

            <div class="table-wrap flex flex-wrap items-center justify-between gap-3 p-4">
                <button type="button" class="btn-sm-secondary" @click="step = 'compose'" :disabled="busy">← Edit message</button>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn-sm-secondary" x-show="mode === 'bulk'" @click="send('draft')" :disabled="busy">
                        <span x-show="!(busy && busyAction === 'draft')">Save draft</span>
                        <span x-show="busy && busyAction === 'draft'" class="inline-flex items-center"><span class="btn-spinner !border-slate-300 !border-t-slate-700"></span> Saving…</span>
                    </button>
                    <button type="button" class="btn-sm-secondary" @click="send('schedule')" :disabled="busy || !canSchedule" x-show="mode === 'bulk' && form.scheduled_at">
                        <span x-show="!(busy && busyAction === 'schedule')">Schedule</span>
                        <span x-show="busy && busyAction === 'schedule'" class="inline-flex items-center"><span class="btn-spinner !border-slate-300 !border-t-slate-700"></span> Scheduling…</span>
                    </button>
                    <button type="button" class="btn-sm-primary min-w-[8.5rem]" @click="send(mode === 'single' ? 'single' : 'launch')" :disabled="busy || !canLaunch">
                        <span x-show="!(busy && (busyAction === 'launch' || busyAction === 'single'))">Send now</span>
                        <span x-show="busy && (busyAction === 'launch' || busyAction === 'single')" class="inline-flex items-center"><span class="btn-spinner"></span> Sending…</span>
                    </button>
                </div>
            </div>
            <p x-show="!canLaunch && preview.valid_count === 0" class="text-sm text-red-600">No valid recipients — go back and fix the list.</p>
            <p x-show="!canLaunch && preview.remaining_balance < 0" class="text-sm text-red-600">Insufficient balance for this send.</p>
        </div>

        {{-- Success summary --}}
        <div x-show="step === 'done'" x-cloak class="table-wrap overflow-hidden">
            <div class="border-b border-emerald-100 bg-emerald-50/80 px-5 py-5">
                <div class="flex items-start gap-3">
                    <div class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-emerald-950" x-text="summary.title"></h2>
                        <p class="mt-1 text-sm text-emerald-900/70">Here’s what happened with this send.</p>
                    </div>
                </div>
            </div>
            <div class="grid gap-px bg-slate-100 sm:grid-cols-2">
                <div class="bg-white px-5 py-4">
                    <div class="text-xs uppercase tracking-wide text-slate-500">Name</div>
                    <div class="mt-1 font-semibold text-slate-900" x-text="summary.name"></div>
                </div>
                <div class="bg-white px-5 py-4">
                    <div class="text-xs uppercase tracking-wide text-slate-500">Status</div>
                    <div class="mt-1 font-semibold capitalize text-slate-900" x-text="summary.status"></div>
                </div>
                <div class="bg-white px-5 py-4">
                    <div class="text-xs uppercase tracking-wide text-slate-500">Valid recipients</div>
                    <div class="mt-1 font-semibold text-emerald-700" x-text="summary.valid_count ?? '—'"></div>
                </div>
                <div class="bg-white px-5 py-4">
                    <div class="text-xs uppercase tracking-wide text-slate-500">Skipped</div>
                    <div class="mt-1 font-semibold text-slate-900">
                        <span x-text="(summary.invalid_count || 0) + (summary.duplicate_count || 0) + (summary.opted_out_count || 0)"></span>
                    </div>
                </div>
                <div class="bg-white px-5 py-4">
                    <div class="text-xs uppercase tracking-wide text-slate-500">Charged</div>
                    <div class="mt-1 font-semibold text-slate-900" x-text="formatMoney(summary.charged || summary.estimated_cost || 0)"></div>
                </div>
                <div class="bg-white px-5 py-4">
                    <div class="text-xs uppercase tracking-wide text-slate-500">Remaining balance</div>
                    <div class="mt-1 font-semibold text-slate-900" x-text="formatMoney(summary.balance)"></div>
                </div>
            </div>
            <template x-if="summary.message_uuid">
                <div class="border-t border-slate-100 px-5 py-3 text-sm text-slate-600">
                    Message ID <span class="font-mono text-slate-800" x-text="summary.message_uuid"></span>
                    <span x-show="summary.to"> · to <span class="font-mono" x-text="summary.to"></span></span>
                </div>
            </template>
            <div class="flex flex-wrap gap-2 border-t border-slate-100 px-5 py-4">
                <a :href="summary.campaign_url" class="btn-sm-primary" x-show="summary.campaign_url">View details</a>
                <button type="button" class="btn-sm-secondary" @click="reset()">Send another</button>
            </div>
        </div>
    </div>
</x-portal-layout>
