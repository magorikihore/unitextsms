<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BillingType;
use App\Enums\ClientRole;
use App\Enums\ClientStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\SmsProvider;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request): View
    {
        $clients = Client::query()
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->q, fn ($q, $term) => $q->where(function ($inner) use ($term) {
                $inner->where('company_name', 'like', "%{$term}%")
                    ->orWhere('contact_email', 'like', "%{$term}%");
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.clients.index', compact('clients'));
    }

    public function create(): View
    {
        return view('admin.clients.create', [
            'providers' => SmsProvider::query()->where('is_active', true)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['status'] = ClientStatus::Pending->value;

        $client = Client::query()->create($data);

        if ($request->filled('owner_email')) {
            $user = User::query()->firstOrCreate(
                ['email' => $request->string('owner_email')],
                [
                    'name' => $request->string('owner_name', $data['contact_name']),
                    'password' => Hash::make($request->string('owner_password', 'ChangeMe123!')),
                    'email_verified_at' => now(),
                ]
            );
            $client->users()->syncWithoutDetaching([
                $user->id => ['role' => ClientRole::Owner->value, 'is_active' => true],
            ]);
        }

        $this->auditLogger->log('client.created', $client, null, $client->toArray(), $request->user(), $client->id);

        return redirect()->route('admin.clients.show', $client)->with('success', 'Client created.');
    }

    public function show(Client $client): View
    {
        $client->load(['users', 'senderIds', 'defaultProvider', 'ledgerEntries' => fn ($q) => $q->latest()->limit(10)]);

        return view('admin.clients.show', [
            'client' => $client,
            'providers' => SmsProvider::query()->where('is_active', true)->get(),
        ]);
    }

    public function edit(Client $client): View
    {
        return view('admin.clients.edit', [
            'client' => $client,
            'providers' => SmsProvider::query()->where('is_active', true)->get(),
        ]);
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $old = $client->toArray();
        $client->update($this->validated($request));
        $this->auditLogger->log('client.updated', $client, $old, $client->fresh()->toArray(), $request->user(), $client->id);

        return back()->with('success', 'Client updated.');
    }

    public function approve(Request $request, Client $client): RedirectResponse
    {
        $client->update([
            'status' => ClientStatus::Active,
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ]);
        $this->auditLogger->log('client.approved', $client, null, ['status' => 'active'], $request->user(), $client->id);

        return back()->with('success', 'Client approved.');
    }

    public function suspend(Request $request, Client $client): RedirectResponse
    {
        $client->update(['status' => ClientStatus::Suspended]);
        $this->auditLogger->log('client.suspended', $client, null, ['status' => 'suspended'], $request->user(), $client->id);

        return back()->with('success', 'Client suspended.');
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'trading_name' => ['nullable', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:8'],
            'billing_type' => ['required', Rule::enum(BillingType::class)],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'price_per_sms' => ['required', 'numeric', 'min:0'],
            'low_balance_threshold' => ['nullable', 'numeric', 'min:0'],
            'daily_send_limit' => ['nullable', 'integer', 'min:1'],
            'monthly_send_limit' => ['nullable', 'integer', 'min:1'],
            'default_provider_id' => ['nullable', 'exists:sms_providers,id'],
            'notes' => ['nullable', 'string'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'owner_email' => ['nullable', 'email'],
            'owner_password' => ['nullable', 'string', 'min:8'],
        ]);

        return array_merge($data, [
            'can_send_otp' => $request->boolean('can_send_otp'),
            'can_send_transactional' => $request->boolean('can_send_transactional'),
            'can_send_marketing' => $request->boolean('can_send_marketing'),
            'api_enabled' => $request->boolean('api_enabled'),
            'allowed_countries' => ['TZ'],
        ]);
    }
}
