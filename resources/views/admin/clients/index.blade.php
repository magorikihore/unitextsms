<x-portal-layout title="Clients" portalLabel="Administration" header="Clients" subheader="Create, approve and manage organisations" :nav="\App\Support\PortalNav::admin('clients')">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <form class="toolbar-row" method="GET">
            <input class="input-field-sm w-56" type="search" name="q" value="{{ request('q') }}" placeholder="Search company or email">
            <select name="status" class="input-field-sm w-auto min-w-[9rem]">
                <option value="">All statuses</option>
                @foreach(['pending','active','suspended','rejected'] as $status)
                    <option value="{{ $status }}" @selected(request('status')===$status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            <button class="btn-sm-secondary">Filter</button>
        </form>
        <a href="{{ route('admin.clients.create') }}" class="btn-sm-primary">New client</a>
    </div>

    <div class="table-wrap">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-4 py-3">Company</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Balance</th>
                <th class="px-4 py-3">Price/SMS</th>
                <th class="px-4 py-3">Contact</th>
                <th class="px-4 py-3">Actions</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @foreach($clients as $client)
                <tr class="hover:bg-slate-50/80">
                    <td class="px-4 py-3">
                        <a class="font-semibold text-teal-700 hover:underline" href="{{ route('admin.clients.show', $client) }}">{{ $client->company_name }}</a>
                    </td>
                    <td class="px-4 py-3"><span class="badge bg-slate-100 text-slate-700">{{ $client->status->value }}</span></td>
                    <td class="px-4 py-3">{{ number_format($client->sms_balance, 2) }}</td>
                    <td class="px-4 py-3">{{ number_format($client->price_per_sms, 4) }}</td>
                    <td class="px-4 py-3">{{ $client->contact_email }}</td>
                    <td class="px-4 py-3">
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('admin.clients.show', $client) }}" class="btn-secondary text-xs">Manage</a>
                            <a href="{{ route('admin.clients.edit', $client) }}" class="btn-secondary text-xs">Update</a>
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $clients->links() }}</div>
</x-portal-layout>
