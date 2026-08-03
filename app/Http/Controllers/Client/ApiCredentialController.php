<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ApiCredential;
use App\Models\Client;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ApiCredentialController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Client $client): View
    {
        return view('client.api.index', [
            'client' => $client,
            'credentials' => ApiCredential::query()->where('client_id', $client->id)->latest()->get(),
            'plainSecret' => session('plain_api_secret'),
            'plainKey' => session('plain_api_key'),
            'plainWebhookSecret' => session('plain_webhook_secret'),
            'apiBase' => rtrim(config('app.url'), '').'/api/v1',
        ]);
    }

    public function store(Request $request, Client $client): RedirectResponse|JsonResponse
    {
        abort_unless($client->api_enabled, 403, 'API access is not enabled for this client.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sandbox' => ['sometimes', 'boolean'],
            'webhook_url' => ['nullable', 'url', 'max:500'],
        ]);

        $keyId = 'uk_'.Str::lower(Str::random(24));
        $secret = 'us_'.Str::lower(Str::random(40));
        $webhookSecret = 'whsec_'.Str::lower(Str::random(32));

        $credential = ApiCredential::query()->create([
            'client_id' => $client->id,
            'name' => $data['name'],
            'key_id' => $keyId,
            'secret_hash' => Hash::make($secret),
            'secret_hint' => substr($secret, -4),
            'sandbox' => $request->boolean('sandbox'),
            'webhook_url' => $data['webhook_url'] ?? null,
            'webhook_signing_secret' => $webhookSecret,
            'is_active' => true,
        ]);

        $this->auditLogger->log('api_key.created', $credential, null, ['key_id' => $keyId], $request->user(), $client->id);

        $message = 'API key created. Copy the secret now — it will not be shown again.';
        session()->flash('plain_api_key', $keyId);
        session()->flash('plain_api_secret', $secret);
        session()->flash('plain_webhook_secret', $webhookSecret);

        return $this->ajaxRedirect($request, route('client.api.index', $client), $message);
    }

    public function updateWebhook(Request $request, Client $client, ApiCredential $credential): RedirectResponse|JsonResponse
    {
        abort_unless($credential->client_id === $client->id, 404);
        abort_unless($credential->is_active, 422, 'Cannot update a revoked key.');

        $data = $request->validate([
            'webhook_url' => ['nullable', 'url', 'max:500'],
            'rotate_webhook_secret' => ['sometimes', 'boolean'],
        ]);

        $old = ['webhook_url' => $credential->webhook_url];
        $updates = ['webhook_url' => $data['webhook_url'] ?: null];
        $plainWebhookSecret = null;

        if ($request->boolean('rotate_webhook_secret') || (! $credential->webhook_signing_secret && $updates['webhook_url'])) {
            $plainWebhookSecret = 'whsec_'.Str::lower(Str::random(32));
            $updates['webhook_signing_secret'] = $plainWebhookSecret;
        }

        $credential->update($updates);

        $this->auditLogger->log('api_webhook.updated', $credential, $old, $updates, $request->user(), $client->id);

        if ($plainWebhookSecret) {
            session()->flash('plain_webhook_secret', $plainWebhookSecret);
            session()->flash('plain_api_key', $credential->key_id);
        }

        return $this->ajaxRedirect($request, route('client.api.index', $client), 'Webhook settings saved.');
    }

    public function revoke(Request $request, Client $client, ApiCredential $credential): RedirectResponse|JsonResponse
    {
        abort_unless($credential->client_id === $client->id, 404);

        $credential->update([
            'is_active' => false,
            'revoked_at' => now(),
        ]);

        $this->auditLogger->log('api_key.revoked', $credential, null, ['key_id' => $credential->key_id], $request->user(), $client->id);

        return $this->ajaxRedirect($request, route('client.api.index', $client), 'API key revoked.');
    }
}
