<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TemplateStatus;
use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TemplateApprovalController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(): View
    {
        return view('admin.approvals.index', [
            'templates' => Template::query()
                ->with('client')
                ->where('status', TemplateStatus::PendingApproval)
                ->latest()
                ->paginate(30),
        ]);
    }

    public function approve(Request $request, Template $template): RedirectResponse
    {
        $template->update([
            'status' => TemplateStatus::Approved,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        $this->auditLogger->log('template.approved', $template, null, ['status' => 'approved'], $request->user(), $template->client_id);

        return back()->with('success', 'Template approved.');
    }

    public function reject(Request $request, Template $template): RedirectResponse
    {
        $template->update(['status' => TemplateStatus::Rejected]);
        $this->auditLogger->log('template.rejected', $template, null, ['status' => 'rejected'], $request->user(), $template->client_id);

        return back()->with('success', 'Template rejected.');
    }
}
