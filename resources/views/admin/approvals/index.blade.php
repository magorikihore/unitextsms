<x-portal-layout title="Approvals" portalLabel="Administration" header="Approvals" subheader="Templates awaiting review" :nav="\App\Support\PortalNav::admin('approvals')">
    <div class="table-wrap overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">Template</th>
                <th class="px-4 py-3">Client</th>
                <th class="px-4 py-3">Category</th>
                <th class="px-4 py-3">Language</th>
                <th class="px-4 py-3">Body</th>
                <th class="px-4 py-3">Actions</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @foreach($templates as $template)
                <tr>
                    <td class="px-4 py-3 font-semibold">{{ $template->name }}</td>
                    <td class="px-4 py-3">{{ $template->client?->company_name }}</td>
                    <td class="px-4 py-3">{{ $template->category->value }}</td>
                    <td class="px-4 py-3">{{ $template->language }}</td>
                    <td class="max-w-md px-4 py-3 text-slate-600">{{ $template->body }}</td>
                    <td class="px-4 py-3">
                        <div class="inline-flex gap-1">
                            <form method="POST" action="{{ route('admin.approvals.templates.approve', $template) }}">@csrf<button class="btn-sm-primary">Approve</button></form>
                            <form method="POST" action="{{ route('admin.approvals.templates.reject', $template) }}">@csrf<button class="btn-sm-secondary text-red-700">Reject</button></form>
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $templates->links() }}</div>
</x-portal-layout>
