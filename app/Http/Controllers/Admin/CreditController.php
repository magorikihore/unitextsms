<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\CreditLedgerEntry;
use App\Services\CreditLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreditController extends Controller
{
    public function __construct(private readonly CreditLedgerService $ledger) {}

    public function index(Request $request): View
    {
        $entries = CreditLedgerEntry::query()
            ->with(['client', 'creator'])
            ->when($request->client_id, fn ($q, $id) => $q->where('client_id', $id))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.credits.index', [
            'entries' => $entries,
            'clients' => Client::query()->orderBy('company_name')->get(['id', 'company_name', 'sms_balance']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'action' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $client = Client::query()->findOrFail($data['client_id']);

        if ($data['action'] === 'credit') {
            $this->ledger->credit($client, (float) $data['amount'], $data['reason'], $request->user());
        } else {
            $this->ledger->debit($client, (float) $data['amount'], $data['reason'], $request->user());
        }

        return back()->with('success', 'Ledger entry recorded.');
    }
}
