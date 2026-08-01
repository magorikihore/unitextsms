<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\CreditLedgerEntry;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(Client $client): View
    {
        return view('client.billing.index', [
            'client' => $client,
            'entries' => CreditLedgerEntry::query()
                ->where('client_id', $client->id)
                ->latest()
                ->paginate(30),
        ]);
    }
}
