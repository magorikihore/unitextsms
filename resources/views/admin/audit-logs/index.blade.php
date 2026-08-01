<x-portal-layout title="Audit logs" portalLabel="Administration" header="Audit logs" :nav="\App\Support\PortalNav::admin('audit')">
    <div class="table-wrap overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">When</th>
                <th class="px-4 py-3">Actor</th>
                <th class="px-4 py-3">Client</th>
                <th class="px-4 py-3">Action</th>
                <th class="px-4 py-3">IP</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @foreach($logs as $log)
                <tr>
                    <td class="px-4 py-3">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                    <td class="px-4 py-3">{{ $log->user?->email ?: 'system' }}</td>
                    <td class="px-4 py-3">{{ $log->client?->company_name ?: '—' }}</td>
                    <td class="px-4 py-3 font-mono text-xs">{{ $log->action }}</td>
                    <td class="px-4 py-3">{{ $log->ip_address }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
</x-portal-layout>
