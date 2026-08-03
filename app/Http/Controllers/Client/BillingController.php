<?php

namespace App\Http\Controllers\Client;

use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\CreditLedgerEntry;
use App\Models\Deposit;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(Request $request, Client $client): View
    {
        $tab = $request->string('tab', 'purchases')->toString();
        if (! in_array($tab, ['purchases', 'all'], true)) {
            $tab = 'purchases';
        }

        $q = trim((string) $request->input('q', ''));
        $method = $request->filled('payment_method') ? (string) $request->input('payment_method') : null;

        $base = CreditLedgerEntry::query()->where('client_id', $client->id);

        if ($tab === 'purchases') {
            $base->where('type', LedgerEntryType::Credit)->whereNotNull('payment_method');
        } elseif ($request->filled('type')) {
            $base->where('type', (string) $request->input('type'));
        }

        $base
            ->when($method, fn ($query) => $query->where('payment_method', $method))
            ->when($q !== '', function ($query) use ($q, $client) {
                $like = '%'.$q.'%';
                $query->where(function ($inner) use ($like, $client) {
                    $inner->where('reference', 'like', $like)
                        ->orWhere('reason', 'like', $like)
                        ->orWhere('meta->receipt_number', 'like', $like)
                        ->orWhere('meta->request_ref', 'like', $like);

                    $matchedDeposit = Deposit::query()
                        ->where('client_id', $client->id)
                        ->where(function ($d) use ($like) {
                            $d->where('reference', 'like', $like)
                                ->orWhere('request_ref', 'like', $like)
                                ->orWhere('receipt_number', 'like', $like)
                                ->orWhere('operator_ref', 'like', $like)
                                ->orWhere('phone', 'like', $like);
                        })
                        ->exists();

                    if ($matchedDeposit) {
                        $inner->orWhere(function ($pay) use ($like) {
                            $pay->where('payment_method', PaymentMethod::Mobile->value)
                                ->where(function ($m) use ($like) {
                                    $m->where('meta->request_ref', 'like', $like)
                                        ->orWhere('meta->receipt_number', 'like', $like)
                                        ->orWhere('reason', 'like', $like)
                                        ->orWhere('reference', 'like', $like);
                                });
                        });
                    }
                });
            });

        $entries = (clone $base)->latest()->paginate(30)->withQueryString();

        $purchasesBase = CreditLedgerEntry::query()
            ->where('client_id', $client->id)
            ->where('type', LedgerEntryType::Credit)
            ->whereNotNull('payment_method')
            ->when($method, fn ($query) => $query->where('payment_method', $method))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(function ($inner) use ($like) {
                    $inner->where('reference', 'like', $like)
                        ->orWhere('reason', 'like', $like)
                        ->orWhere('meta->receipt_number', 'like', $like)
                        ->orWhere('meta->request_ref', 'like', $like);
                });
            });

        $stats = [
            'total' => (float) (clone $purchasesBase)->sum('amount'),
            'count' => (clone $purchasesBase)->count(),
            'cash' => (float) CreditLedgerEntry::query()
                ->where('client_id', $client->id)
                ->where('type', LedgerEntryType::Credit)
                ->where('payment_method', PaymentMethod::Cash)
                ->sum('amount'),
            'bank' => (float) CreditLedgerEntry::query()
                ->where('client_id', $client->id)
                ->where('type', LedgerEntryType::Credit)
                ->where('payment_method', PaymentMethod::Bank)
                ->sum('amount'),
            'mobile' => (float) CreditLedgerEntry::query()
                ->where('client_id', $client->id)
                ->where('type', LedgerEntryType::Credit)
                ->where('payment_method', PaymentMethod::Mobile)
                ->sum('amount'),
        ];

        $deposits = Deposit::query()
            ->where('client_id', $client->id)
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(function ($d) use ($like) {
                    $d->where('reference', 'like', $like)
                        ->orWhere('request_ref', 'like', $like)
                        ->orWhere('receipt_number', 'like', $like)
                        ->orWhere('operator_ref', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                });
            })
            ->when($method !== null && $method !== PaymentMethod::Mobile->value, fn ($query) => $query->whereRaw('1 = 0'))
            ->latest()
            ->limit(10)
            ->get();

        return view('client.billing.index', [
            'client' => $client,
            'entries' => $entries,
            'deposits' => $deposits,
            'tab' => $tab,
            'q' => $q,
            'stats' => $stats,
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }
}
