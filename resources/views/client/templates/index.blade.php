<x-portal-layout title="Templates" portalLabel="{{ $client->company_name }}" header="Templates" :nav="\App\Support\PortalNav::client($client, 'templates')">
    <form method="POST" action="{{ route('client.templates.store', $client) }}" data-ajax class="table-wrap mb-6 max-w-3xl space-y-3 p-5" novalidate>
        @csrf
        <div data-form-error class="hidden rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-sm text-red-700"></div>
        <div class="grid gap-3 sm:grid-cols-3">
            <div>
                <input name="name" class="input-field" placeholder="Template name">
                <p class="field-error hidden" data-error-for="name"></p>
            </div>
            <div>
                <select name="category" class="input-field">
                    <option value="otp">OTP</option>
                    <option value="transactional">Transactional</option>
                    <option value="marketing">Bulk SMS</option>
                </select>
                <p class="field-error hidden" data-error-for="category"></p>
            </div>
            <div>
                <select name="language" class="input-field">
                    <option value="en">English</option>
                    <option value="sw">Swahili</option>
                </select>
                <p class="field-error hidden" data-error-for="language"></p>
            </div>
        </div>
        <div>
            <textarea name="body" rows="4" class="input-field" placeholder="Habari @{{first_name}}, ..."></textarea>
            <p class="field-error hidden" data-error-for="body"></p>
        </div>
        <button class="btn-primary" data-loading-text="Submitting…">Submit for approval</button>
    </form>

    <div class="table-wrap overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">Name</th>
                <th class="px-4 py-3">Category</th>
                <th class="px-4 py-3">Lang</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Body</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @foreach($templates as $template)
                <tr>
                    <td class="px-4 py-3 font-semibold">{{ $template->name }}</td>
                    <td class="px-4 py-3">{{ $template->category->label() }}</td>
                    <td class="px-4 py-3">{{ $template->language }}</td>
                    <td class="px-4 py-3">{{ $template->status->value }}</td>
                    <td class="px-4 py-3 max-w-md truncate">{{ $template->body }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $templates->links() }}</div>
</x-portal-layout>
