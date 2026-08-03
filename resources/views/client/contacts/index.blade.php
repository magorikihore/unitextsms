<x-portal-layout title="Contacts" portalLabel="{{ $client->company_name }}" header="Contacts" :nav="\App\Support\PortalNav::client($client, 'contacts')">
    <div class="grid gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('client.contacts.store', $client) }}" data-ajax class="table-wrap space-y-3 p-5" novalidate>
            @csrf
            <h2 class="font-semibold">Add contact</h2>
            <div data-form-error class="hidden rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-sm text-red-700"></div>
            <div>
                <input name="phone" class="input-field" placeholder="0758...">
                <p class="field-error hidden" data-error-for="phone"></p>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <input name="first_name" class="input-field" placeholder="First name">
                    <p class="field-error hidden" data-error-for="first_name"></p>
                </div>
                <div>
                    <input name="last_name" class="input-field" placeholder="Last name">
                    <p class="field-error hidden" data-error-for="last_name"></p>
                </div>
            </div>
            <div>
                <input type="email" name="email" class="input-field" placeholder="Email">
                <p class="field-error hidden" data-error-for="email"></p>
            </div>
            <button class="btn-primary" data-loading-text="Saving…">Save contact</button>
        </form>

        <form method="POST" action="{{ route('client.contacts.import', $client) }}" enctype="multipart/form-data" data-ajax class="table-wrap space-y-3 p-5" novalidate>
            @csrf
            <h2 class="font-semibold">Import CSV / Excel</h2>
            <div data-form-error class="hidden rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-sm text-red-700"></div>
            <p class="text-sm text-slate-500">Columns: phone, first_name, last_name, email</p>
            <div>
                <input type="file" name="file" accept=".csv,.xlsx,.xls,.txt">
                <p class="field-error hidden" data-error-for="file"></p>
            </div>
            <button class="btn-primary" data-loading-text="Importing…">Import</button>
            <div class="pt-3 text-sm">
                <h3 class="font-medium">Import history</h3>
                @foreach($imports as $batch)
                    <div class="border-b border-slate-100 py-2">{{ $batch->filename }} · {{ $batch->imported }} imported / {{ $batch->duplicates }} dup / {{ $batch->invalid }} invalid</div>
                @endforeach
            </div>
        </form>
    </div>

    <div class="table-wrap mt-8 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">Phone</th>
                <th class="px-4 py-3">Name</th>
                <th class="px-4 py-3">Consent</th>
                <th class="px-4 py-3">Opt-out</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @foreach($contacts as $contact)
                <tr>
                    <td class="px-4 py-3">{{ $contact->phone_normalized }}</td>
                    <td class="px-4 py-3">{{ trim(($contact->first_name.' '.$contact->last_name)) ?: '—' }}</td>
                    <td class="px-4 py-3">{{ $contact->consent_status }}</td>
                    <td class="px-4 py-3">{{ $contact->is_opted_out ? 'yes' : 'no' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $contacts->links() }}</div>
</x-portal-layout>
