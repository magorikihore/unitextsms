<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\SystemSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SystemSettingsService $settings,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(): View
    {
        return view('admin.settings.index', [
            'settings' => $this->settings->current(),
        ]);
    }

    public function update(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'provider_tps' => ['required', 'integer', 'min:1', 'max:1000'],
            'otp_tps' => ['required', 'integer', 'min:1', 'max:1000'],
            'transactional_tps' => ['required', 'integer', 'min:1', 'max:1000'],
            'bulk_tps' => ['required', 'integer', 'min:0', 'max:1000'],
            'otp_ttl_minutes' => ['required', 'integer', 'min:1', 'max:30'],
            'otp_latency_pause_ms' => ['required', 'integer', 'min:100', 'max:60000'],
            'otp_failure_pause_rate' => ['required', 'numeric', 'min:0.01', 'max:1'],
            'marketing_hours_start' => ['required', 'date_format:H:i'],
            'marketing_hours_end' => ['required', 'date_format:H:i'],
            'marketing_timezone' => ['required', 'timezone'],
            'bulk_paused' => ['sometimes', 'boolean'],
            'dlr_allowed_ips' => ['nullable', 'string', 'max:2000'],
            'mail_mailer' => ['required', Rule::in(['log', 'mailgun', 'smtp'])],
            'mailgun_domain' => ['nullable', 'string', 'max:255'],
            'mailgun_secret' => ['nullable', 'string', 'max:255'],
            'mailgun_endpoint' => ['nullable', 'string', 'max:255'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
            'payin_enabled' => ['sometimes', 'boolean'],
            'payin_base_url' => ['nullable', 'url', 'max:500'],
            'payin_api_key' => ['nullable', 'string', 'max:255'],
            'payin_api_secret' => ['nullable', 'string', 'max:255'],
            'payin_webhook_secret' => ['nullable', 'string', 'max:255'],
            'payin_currency' => ['nullable', 'string', 'max:8'],
            'payin_min_amount' => ['nullable', 'integer', 'min:1'],
        ]);

        $data['bulk_paused'] = $request->boolean('bulk_paused');
        $data['payin_enabled'] = $request->boolean('payin_enabled');
        $before = $this->settings->current();

        try {
            $this->settings->update($data);
        } catch (\Throwable $e) {
            return $this->ajaxBack($request, $e->getMessage(), ['provider_tps' => $e->getMessage()]);
        }

        $after = $this->settings->current();
        unset(
            $before['mailgun_secret'],
            $before['payin_api_key'],
            $before['payin_api_secret'],
            $before['payin_webhook_secret'],
            $after['mailgun_secret'],
            $after['payin_api_key'],
            $after['payin_api_secret'],
            $after['payin_webhook_secret'],
        );

        $this->auditLogger->log('system_settings.updated', null, $before, $after, $request->user());

        return $this->ajaxRedirect($request, route('admin.settings.index'), 'System settings saved.');
    }
}
