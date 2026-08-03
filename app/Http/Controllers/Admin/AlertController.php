<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\SystemAlert;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlertController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request): View
    {
        $alerts = SystemAlert::query()
            ->with('client')
            ->when($request->severity, fn ($q, $severity) => $q->where('severity', $severity))
            ->when($request->type, fn ($q, $type) => $q->where('type', $type))
            ->when($request->filled('resolved'), function ($q) use ($request) {
                $q->where('is_resolved', $request->boolean('resolved'));
            }, function ($q) {
                $q->where('is_resolved', false);
            })
            ->latest()
            ->paginate(40)
            ->withQueryString();

        return view('admin.alerts.index', [
            'alerts' => $alerts,
            'clients' => Client::query()->orderBy('company_name')->get(['id', 'company_name', 'contact_email', 'contact_phone', 'sms_balance']),
            'openCount' => SystemAlert::query()->where('is_resolved', false)->count(),
            'filters' => $request->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['nullable', 'exists:clients,id'],
            'type' => ['required', 'string', 'max:80'],
            'severity' => ['required', 'in:info,warning,critical'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $alert = SystemAlert::query()->create($data + ['is_resolved' => false]);
        $this->auditLogger->log('alert.created', $alert, null, $alert->toArray(), $request->user(), $alert->client_id);

        return $this->ajaxRedirect($request, route('admin.alerts.index'), 'Alert created.');
    }

    public function resolve(Request $request, SystemAlert $alert): RedirectResponse
    {
        $alert->update([
            'is_resolved' => true,
            'resolved_at' => now(),
        ]);

        $this->auditLogger->log('alert.resolved', $alert, null, ['resolved_at' => now()->toIso8601String()], $request->user(), $alert->client_id);

        return $this->ajaxBack($request, 'Alert resolved.');
    }
}
