<x-portal-layout title="API" portalLabel="{{ $client->company_name }}" header="API & Webhooks" :nav="\App\Support\PortalNav::client($client, 'api')">
    @if($plainKey && $plainSecret)
        <div class="mb-4 rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900">
            <p class="font-semibold">Copy these credentials now. The secret will not be shown again.</p>
            <p class="mt-2 font-mono">Key: {{ $plainKey }}</p>
            <p class="font-mono">Secret: {{ $plainSecret }}</p>
            @if(session('plain_webhook_secret'))
                <p class="font-mono">Webhook signing secret: {{ session('plain_webhook_secret') }}</p>
            @endif
        </div>
    @endif

    @if($client->api_enabled)
        <form method="POST" action="{{ route('client.api.store', $client) }}" class="table-wrap mb-6 grid gap-3 p-5 md:grid-cols-3">
            @csrf
            <input name="name" class="input-field" placeholder="Key name" required>
            <input name="webhook_url" class="input-field" placeholder="Delivery webhook URL">
            <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="sandbox" value="1"> Sandbox mode</label>
            <button class="btn-primary md:col-span-3">Generate API key</button>
        </form>
    @else
        <div class="mb-6 rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-600">API access is disabled for this organisation. Ask the platform administrator to enable it.</div>
    @endif

    <div class="table-wrap overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">Name</th>
                <th class="px-4 py-3">Key</th>
                <th class="px-4 py-3">Hint</th>
                <th class="px-4 py-3">Sandbox</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3"></th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @foreach($credentials as $credential)
                <tr>
                    <td class="px-4 py-3">{{ $credential->name }}</td>
                    <td class="px-4 py-3 font-mono text-xs">{{ $credential->key_id }}</td>
                    <td class="px-4 py-3">••••{{ $credential->secret_hint }}</td>
                    <td class="px-4 py-3">{{ $credential->sandbox ? 'yes' : 'no' }}</td>
                    <td class="px-4 py-3">{{ $credential->is_active ? 'active' : 'revoked' }}</td>
                    <td class="px-4 py-3">
                        @if($credential->is_active)
                            <form method="POST" action="{{ route('client.api.revoke', [$client, $credential]) }}">@csrf @method('DELETE')<button class="btn-danger">Revoke</button></form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-portal-layout>
