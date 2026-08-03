<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\CreditLedgerEntry;
use App\Models\Deposit;
use App\Services\CreditLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CreditController extends Controller
{
    public function __construct(private readonly CreditLedgerService $ledger) {}

    public function index(Request $request): View
    {
        $tab = $request->string('tab', 'payments')->toString();
        if (! in_array($tab, ['payments', 'all'], true)) {
            $tab = 'payments';
        }

        $q = trim((string) $request->input('q', ''));
        $clientId = $request->filled('client_id') ? $request->integer('client_id') : null;
        $method = $request->filled('payment_method') ? (string) $request->input('payment_method') : null;

        $base = CreditLedgerEntry::query()->with(['client', 'creator']);

        if ($tab === 'payments') {
            $base->where('type', LedgerEntryType::Credit)->whereNotNull('payment_method');
        } elseif ($request->filled('type')) {
            $base->where('type', (string) $request->input('type'));
        }

        $base
            ->when($clientId, fn ($query) => $query->where('client_id', $clientId))
            ->when($method, fn ($query) => $query->where('payment_method', $method))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(function ($inner) use ($like, $q) {
                    $inner->where('reference', 'like', $like)
                        ->orWhere('reason', 'like', $like)
                        ->orWhere('meta->receipt_number', 'like', $like)
                        ->orWhere('meta->request_ref', 'like', $like)
                        ->orWhereHas('client', function ($client) use ($like) {
                            $client->where('company_name', 'like', $like)
                                ->orWhere('trading_name', 'like', $like)
                                ->orWhere('contact_email', 'like', $like)
                                ->orWhere('contact_phone', 'like', $like);
                        });

                    $depositClientIds = Deposit::query()
                        ->where(function ($d) use ($like) {
                            $d->where('reference', 'like', $like)
                                ->orWhere('request_ref', 'like', $like)
                                ->orWhere('receipt_number', 'like', $like)
                                ->orWhere('operator_ref', 'like', $like)
                                ->orWhere('phone', 'like', $like);
                        })
                        ->pluck('client_id');

                    if ($depositClientIds->isNotEmpty()) {
                        $inner->orWhere(function ($pay) use ($depositClientIds, $like) {
                            $pay->whereIn('client_id', $depositClientIds)
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

        $entries = (clone $base)->latest()->paginate(40)->withQueryString();

        $paymentsBase = CreditLedgerEntry::query()
            ->where('type', LedgerEntryType::Credit)
            ->whereNotNull('payment_method')
            ->when($clientId, fn ($query) => $query->where('client_id', $clientId))
            ->when($method, fn ($query) => $query->where('payment_method', $method));

        $stats = [
            'total' => (float) (clone $paymentsBase)->sum('amount'),
            'count' => (clone $paymentsBase)->count(),
            'cash' => (float) (clone $paymentsBase)->where('payment_method', PaymentMethod::Cash)->sum('amount'),
            'bank' => (float) (clone $paymentsBase)->where('payment_method', PaymentMethod::Bank)->sum('amount'),
            'mobile' => (float) (clone $paymentsBase)->where('payment_method', PaymentMethod::Mobile)->sum('amount'),
        ];

        $deposits = Deposit::query()
            ->with('client')
            ->when($clientId, fn ($query) => $query->where('client_id', $clientId))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(function ($d) use ($like) {
                    $d->where('reference', 'like', $like)
                        ->orWhere('request_ref', 'like', $like)
                        ->orWhere('receipt_number', 'like', $like)
                        ->orWhere('operator_ref', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhereHas('client', function ($client) use ($like) {
                            $client->where('company_name', 'like', $like)
                                ->orWhere('contact_email', 'like', $like);
                        });
                });
            })
            ->when($method === PaymentMethod::Mobile->value || $method === null, fn ($query) => $query)
            ->when($method !== null && $method !== PaymentMethod::Mobile->value, fn ($query) => $query->whereRaw('1 = 0'))
            ->latest()
            ->limit(15)
            ->get();

        return view('admin.credits.index', [
            'entries' => $entries,
            'deposits' => $deposits,
            'stats' => $stats,
            'tab' => $tab,
            'q' => $q,
            'clients' => Client::query()
                ->orderBy('company_name')
                ->get(['id', 'company_name', 'contact_email', 'contact_phone', 'sms_balance']),
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'action' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:255'],
            'payment_method' => [
                Rule::requiredIf(fn () => $request->input('action') === 'credit'),
                'nullable',
                Rule::in([PaymentMethod::Cash->value, PaymentMethod::Bank->value]),
            ],
            'receipt' => ['nullable', 'string', 'max:100'],
        ]);

        $client = Client::query()->findOrFail($data['client_id']);

        if ($data['action'] === 'credit') {
            $method = PaymentMethod::from($data['payment_method']);
            $receipt = trim((string) ($data['receipt'] ?? ''));
            $reason = $data['reason'];
            if ($receipt !== '' && ! str_contains(strtolower($reason), strtolower($receipt))) {
                $reason .= ' · receipt '.$receipt;
            }

            $this->ledger->credit(
                $client,
                (float) $data['amount'],
                $reason,
                $request->user(),
                null,
                array_filter([
                    'payment_method' => $method->value,
                    'source' => 'admin_manual',
                    'receipt_number' => $receipt !== '' ? $receipt : null,
                ]),
                $method,
            );
        } else {
            $this->ledger->debit($client, (float) $data['amount'], $data['reason'], $request->user());
        }

        return $this->ajaxRedirect(
            $request,
            route('admin.credits.index', ['client_id' => $client->id, 'tab' => 'payments']),
            'Ledger entry recorded.',
        );
    }
}
