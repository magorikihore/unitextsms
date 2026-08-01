<x-portal-layout title="Sender IDs" portalLabel="Administration" header="Sender ID management" :nav="\App\Support\PortalNav::admin('sender-ids')">
    <form class="mb-4 flex gap-2" method="GET">
        <select name="status" class="input-field max-w-xs">
            <option value="">All statuses</option>
            @foreach(['pending','approved','rejected','suspended','expired'] as $status)
                <option value="{{ $status }}" @selected(request('status')===$status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <button class="btn-secondary">Filter</button>
    </form>

    <div class="table-wrap overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">Sender</th>
                <th class="px-4 py-3">Client</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Category</th>
                <th class="px-4 py-3">Actions</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @foreach($senderIds as $sid)
                <tr>
                    <td class="px-4 py-3 font-semibold">{{ $sid->sender }}</td>
                    <td class="px-4 py-3">{{ $sid->client?->company_name }}</td>
                    <td class="px-4 py-3">{{ $sid->status->value }}</td>
                    <td class="px-4 py-3">{{ $sid->category?->value ?: 'any' }}</td>
                    <td class="px-4 py-3">
                        <div class="flex flex-wrap gap-2">
                            @if($sid->status->value !== 'approved')
                                <form method="POST" action="{{ route('admin.sender-ids.approve', $sid) }}">@csrf<button class="btn-primary">Approve</button></form>
                            @endif
                            @if($sid->status->value === 'pending')
                                <form method="POST" action="{{ route('admin.sender-ids.reject', $sid) }}" class="flex gap-1">
                                    @csrf
                                    <input name="rejection_reason" class="input-field" placeholder="Reason" required>
                                    <button class="btn-danger">Reject</button>
                                </form>
                            @endif
                            @if($sid->status->value === 'approved')
                                <form method="POST" action="{{ route('admin.sender-ids.suspend', $sid) }}">@csrf<button class="btn-secondary">Suspend</button></form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $senderIds->links() }}</div>
</x-portal-layout>
