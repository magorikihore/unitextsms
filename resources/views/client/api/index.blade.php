<x-portal-layout title="API" portalLabel="{{ $client->company_name }}" header="API & Webhooks" subheader="Create keys, send SMS via HTTP, and receive delivery reports" :nav="\App\Support\PortalNav::client($client, 'api')">
    @if($plainKey && $plainSecret)
        <div class="mb-4 rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900">
            <p class="font-semibold">Copy these credentials now. The secret will not be shown again.</p>
            <p class="mt-2 font-mono break-all">Key: {{ $plainKey }}</p>
            <p class="font-mono break-all">Secret: {{ $plainSecret }}</p>
            @if($plainWebhookSecret)
                <p class="font-mono break-all">Webhook signing secret: {{ $plainWebhookSecret }}</p>
            @endif
        </div>
    @elseif($plainWebhookSecret)
        <div class="mb-4 rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900">
            <p class="font-semibold">New webhook signing secret (copy now):</p>
            <p class="mt-2 font-mono break-all">{{ $plainWebhookSecret }}</p>
            @if($plainKey)
                <p class="mt-1 text-xs">Key: {{ $plainKey }}</p>
            @endif
        </div>
    @endif

    @if($client->api_enabled)
        <form method="POST" action="{{ route('client.api.store', $client) }}" data-ajax class="table-wrap mb-6 grid gap-3 p-5 md:grid-cols-2" novalidate>
            @csrf
            <div data-form-error class="hidden md:col-span-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></div>
            <div>
                <input name="name" class="input-field" placeholder="Key name (e.g. Production app)">
                <p class="field-error hidden" data-error-for="name"></p>
            </div>
            <div>
                <input name="webhook_url" class="input-field" placeholder="DLR webhook URL (https://...)" type="url">
                <p class="field-error hidden" data-error-for="webhook_url"></p>
            </div>
            <label class="inline-flex items-center gap-2 text-sm text-slate-600 md:col-span-2">
                <input type="checkbox" name="sandbox" value="1" class="rounded border-slate-300"> Sandbox mode
            </label>
            <button type="submit" data-loading-text="Creating…" class="btn-primary md:col-span-2">Generate API key + webhook secret</button>
        </form>
    @else
        <div class="mb-6 rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-600">API access is disabled for this organisation. Ask the platform administrator to enable it.</div>
    @endif

    <div class="table-wrap mb-8 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">Name</th>
                <th class="px-4 py-3">Key</th>
                <th class="px-4 py-3">Secret</th>
                <th class="px-4 py-3">Webhook</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Actions</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($credentials as $credential)
                <tr class="align-top">
                    <td class="px-4 py-3">
                        <div class="font-medium">{{ $credential->name }}</div>
                        <div class="text-xs text-slate-500">{{ $credential->sandbox ? 'sandbox' : 'live' }}</div>
                    </td>
                    <td class="px-4 py-3 font-mono text-xs break-all">{{ $credential->key_id }}</td>
                    <td class="px-4 py-3">••••{{ $credential->secret_hint }}</td>
                    <td class="px-4 py-3 min-w-[16rem]">
                        <form method="POST" action="{{ route('client.api.webhook', [$client, $credential]) }}" class="space-y-2">
                            @csrf
                            @method('PUT')
                            <input type="url" name="webhook_url" class="input-field text-xs" placeholder="https://your-app.com/webhooks/sms" value="{{ $credential->webhook_url }}">
                            <label class="flex items-center gap-2 text-xs text-slate-600">
                                <input type="checkbox" name="rotate_webhook_secret" value="1" class="rounded border-slate-300">
                                Rotate signing secret
                            </label>
                            <button class="btn-secondary text-xs">Save webhook</button>
                        </form>
                    </td>
                    <td class="px-4 py-3">{{ $credential->is_active ? 'active' : 'revoked' }}</td>
                    <td class="px-4 py-3">
                        @if($credential->is_active)
                            <form method="POST" action="{{ route('client.api.revoke', [$client, $credential]) }}">@csrf @method('DELETE')<button class="btn-danger">Revoke</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-6 text-slate-500">No API keys yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <section class="table-wrap space-y-6 p-6 text-sm text-slate-700">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">API documentation</h2>
            <p class="mt-1 text-slate-500">Base URL: <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs">{{ $apiBase }}</code></p>
        </div>

        <div>
            <h3 class="font-semibold text-slate-900">Authentication</h3>
            <p class="mt-1">Send both key and secret on every request:</p>
            <pre class="mt-2 overflow-x-auto rounded-lg bg-slate-900 p-4 text-xs text-slate-100">X-Api-Key: uk_xxxxxxxx
X-Api-Secret: us_xxxxxxxx

# or HTTP Basic (key as username, secret as password)
Authorization: Basic base64(uk_xxx:us_xxx)</pre>
        </div>

        <div>
            <h3 class="font-semibold text-slate-900">GET /api/v1/me</h3>
            <p class="mt-1">Account balance and approved sender IDs.</p>
            <pre class="mt-2 overflow-x-auto rounded-lg bg-slate-900 p-4 text-xs text-slate-100">curl -s {{ $apiBase }}/me \
  -H "X-Api-Key: YOUR_KEY" \
  -H "X-Api-Secret: YOUR_SECRET"</pre>
        </div>

        <div>
            <h3 class="font-semibold text-slate-900">POST /api/v1/sms</h3>
            <p class="mt-1">Queue an SMS. Returns HTTP 202 with a message UUID.</p>
            <pre class="mt-2 overflow-x-auto rounded-lg bg-slate-900 p-4 text-xs text-slate-100">curl -s -X POST {{ $apiBase }}/sms \
  -H "X-Api-Key: YOUR_KEY" \
  -H "X-Api-Secret: YOUR_SECRET" \
  -H "Content-Type: application/json" \
  -d '{
    "to": "0758460813",
    "from": "YOURSENDER",
    "message": "Your code is 123456",
    "category": "otp"
  }'

# category: otp | transactional | alert | marketing (Bulk SMS)
# aliases: bulksms | bulk | trxn</pre>
        </div>

        <div>
            <h3 class="font-semibold text-slate-900">GET /api/v1/sms/{uuid}</h3>
            <p class="mt-1">Check delivery status for a message you sent.</p>
            <pre class="mt-2 overflow-x-auto rounded-lg bg-slate-900 p-4 text-xs text-slate-100">curl -s {{ $apiBase }}/sms/MESSAGE_UUID \
  -H "X-Api-Key: YOUR_KEY" \
  -H "X-Api-Secret: YOUR_SECRET"</pre>
        </div>

        <div>
            <h3 class="font-semibold text-slate-900">DLR webhooks (we call you)</h3>
            <p class="mt-1">Set a webhook URL on your API key. When Jasmin reports delivery, we POST to your URL.</p>
            <pre class="mt-2 overflow-x-auto rounded-lg bg-slate-900 p-4 text-xs text-slate-100">POST https://your-app.com/webhooks/sms
Content-Type: application/json
X-UniNText-Event: sms.dlr
X-UniNText-Signature: sha256=HMAC_SHA256(raw_body, webhook_signing_secret)

{
  "event": "sms.dlr",
  "message_uuid": "....",
  "to": "255758460813",
  "from": "YOURSENDER",
  "status": "delivered",
  "provider_status": "DELIVRD",
  "units": 1,
  "delivered_at": "2026-08-01T21:00:00+00:00"
}</pre>
            <p class="mt-2 text-slate-500">Verify the signature with your webhook signing secret. Respond with HTTP 2xx. We retry failed deliveries.</p>
        </div>
    </section>
</x-portal-layout>
