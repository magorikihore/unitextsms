<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmsProvider;
use App\Models\SmsRoute;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProviderController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(): View
    {
        return view('admin.providers.index', [
            'providers' => SmsProvider::query()->with('routes')->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'driver' => ['required', 'in:log,jasmin'],
            'rate_limit_per_second' => ['required', 'integer', 'min:1', 'max:100'],
            'cost_per_sms' => ['required', 'numeric', 'min:0'],
            'balance' => ['nullable', 'numeric'],
            'username' => ['nullable', 'string'],
            'password' => ['nullable', 'string'],
            'base_url' => ['nullable', 'url'],
            'webhook_url' => ['nullable', 'url'],
        ]);

        $provider = SmsProvider::query()->create([
            'name' => $data['name'],
            'driver' => $data['driver'],
            'is_active' => true,
            'rate_limit_per_second' => $data['rate_limit_per_second'],
            'cost_per_sms' => $data['cost_per_sms'],
            'balance' => $data['balance'] ?? null,
            'webhook_url' => $data['webhook_url'] ?? null,
            'credentials' => array_filter([
                'username' => $data['username'] ?? null,
                'password' => $data['password'] ?? null,
                'base_url' => $data['base_url'] ?? null,
            ]),
            'health_status' => 'unknown',
        ]);

        foreach (['otp', 'transactional', 'marketing'] as $i => $category) {
            SmsRoute::query()->create([
                'sms_provider_id' => $provider->id,
                'name' => strtoupper($category).' TZ',
                'category' => $category === 'marketing' ? 'marketing' : $category,
                'country_code' => 'TZ',
                'priority' => $i + 1,
                'is_active' => true,
            ]);
        }

        $this->auditLogger->log('provider.created', $provider, null, ['name' => $provider->name], $request->user());

        return $this->ajaxRedirect($request, route('admin.providers.index'), 'Provider configured.');
    }
}
