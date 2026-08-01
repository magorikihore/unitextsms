<x-portal-layout title="Traffic" portalLabel="Administration" header="SMS Traffic" subheader="Queue priorities: OTP → transactional → alerts → bulk" :nav="\App\Support\PortalNav::admin('traffic')">
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($queues as $name => $count)
            <x-stat :label="$name" :value="$count.' queued'" />
        @endforeach
    </div>
    <div class="table-wrap overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">UUID</th>
                <th class="px-4 py-3">Client</th>
                <th class="px-4 py-3">To</th>
                <th class="px-4 py-3">Queue</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">When</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @foreach($messages as $message)
                <tr>
                    <td class="px-4 py-3 font-mono text-xs">{{ Str::limit($message->uuid, 13) }}</td>
                    <td class="px-4 py-3">{{ $message->client?->company_name }}</td>
                    <td class="px-4 py-3">{{ $message->to_phone_normalized }}</td>
                    <td class="px-4 py-3">{{ $message->queue_name }}</td>
                    <td class="px-4 py-3">{{ $message->status->value }}</td>
                    <td class="px-4 py-3">{{ $message->created_at->diffForHumans() }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $messages->links() }}</div>
</x-portal-layout>
