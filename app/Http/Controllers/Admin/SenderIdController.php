<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SenderIdStatus;
use App\Http\Controllers\Controller;
use App\Models\SenderId;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SenderIdController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request): View
    {
        $senderIds = SenderId::query()
            ->with('client')
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.sender-ids.index', compact('senderIds'));
    }

    public function approve(Request $request, SenderId $senderId): RedirectResponse
    {
        $senderId->update([
            'status' => SenderIdStatus::Approved,
            'approved_at' => now(),
            'reviewed_by' => $request->user()->id,
            'provider_status' => $request->input('provider_status', 'approved'),
            'regulator_status' => $request->input('regulator_status', 'approved'),
            'expires_at' => $request->input('expires_at'),
            'rejection_reason' => null,
        ]);

        $this->auditLogger->log('sender_id.approved', $senderId, null, $senderId->toArray(), $request->user(), $senderId->client_id);

        return back()->with('success', 'Sender ID approved.');
    }

    public function reject(Request $request, SenderId $senderId): RedirectResponse
    {
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:500']]);

        $senderId->update([
            'status' => SenderIdStatus::Rejected,
            'rejection_reason' => $data['rejection_reason'],
            'reviewed_by' => $request->user()->id,
        ]);

        $this->auditLogger->log('sender_id.rejected', $senderId, null, $data, $request->user(), $senderId->client_id);

        return back()->with('success', 'Sender ID rejected.');
    }

    public function suspend(Request $request, SenderId $senderId): RedirectResponse
    {
        $senderId->update([
            'status' => SenderIdStatus::Suspended,
            'reviewed_by' => $request->user()->id,
        ]);

        $this->auditLogger->log('sender_id.suspended', $senderId, null, ['status' => 'suspended'], $request->user(), $senderId->client_id);

        return back()->with('success', 'Sender ID suspended.');
    }
}
