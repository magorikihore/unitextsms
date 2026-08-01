<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = AuditLog::query()
            ->with(['user', 'client'])
            ->when($request->action, fn ($q, $action) => $q->where('action', 'like', "%{$action}%"))
            ->when($request->client_id, fn ($q, $id) => $q->where('client_id', $id))
            ->latest()
            ->paginate(40)
            ->withQueryString();

        return view('admin.audit-logs.index', compact('logs'));
    }
}
