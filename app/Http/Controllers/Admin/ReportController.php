<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Message;
use App\Models\SmsProvider;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $query = $this->filtered($request);

        $stats = [
            'submitted' => (clone $query)->count(),
            'delivered' => (clone $query)->where('status', 'delivered')->count(),
            'failed' => (clone $query)->where('status', 'failed')->count(),
            'rejected' => (clone $query)->where('status', 'rejected')->count(),
            'expired' => (clone $query)->where('status', 'expired')->count(),
            'units' => (clone $query)->sum('units'),
            'client_charge' => (clone $query)->sum('client_charge'),
            'provider_cost' => (clone $query)->sum('provider_cost'),
            'avg_delivery_ms' => (clone $query)->whereNotNull('delivery_ms')->avg('delivery_ms'),
        ];
        $stats['delivery_pct'] = $stats['submitted'] > 0
            ? round(($stats['delivered'] / $stats['submitted']) * 100, 2)
            : 0;
        $stats['gross_profit'] = (float) $stats['client_charge'] - (float) $stats['provider_cost'];

        return view('admin.reports.index', [
            'messages' => $query->latest()->paginate(50)->withQueryString(),
            'stats' => $stats,
            'clients' => Client::query()->orderBy('company_name')->get(['id', 'company_name']),
            'providers' => SmsProvider::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->all(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filename = 'sms-report-'.now()->format('Ymd-His').'.csv';
        $messages = $this->filtered($request)->latest()->limit(10000)->get();

        return response()->streamDownload(function () use ($messages) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'UUID', 'Client', 'Campaign', 'Sender', 'Phone', 'Category', 'Status',
                'Units', 'Client Charge', 'Provider Cost', 'Failure Reason', 'Created At', 'Delivered At',
            ]);
            foreach ($messages as $message) {
                fputcsv($out, [
                    $message->uuid,
                    $message->client?->company_name,
                    $message->campaign_id,
                    $message->from_sender,
                    $message->to_phone_normalized,
                    $message->category?->value,
                    $message->status?->value,
                    $message->units,
                    $message->client_charge,
                    $message->provider_cost,
                    $message->failure_reason,
                    $message->created_at,
                    $message->delivered_at,
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    protected function filtered(Request $request)
    {
        return Message::query()
            ->with(['client', 'campaign', 'provider'])
            ->when($request->client_id, fn ($q, $id) => $q->where('client_id', $id))
            ->when($request->campaign_id, fn ($q, $id) => $q->where('campaign_id', $id))
            ->when($request->sender, fn ($q, $sender) => $q->where('from_sender', $sender))
            ->when($request->phone, fn ($q, $phone) => $q->where('to_phone_normalized', 'like', "%{$phone}%"))
            ->when($request->provider_id, fn ($q, $id) => $q->where('sms_provider_id', $id))
            ->when($request->category, fn ($q, $cat) => $q->where('category', $cat))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->from, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($request->to, fn ($q, $to) => $q->whereDate('created_at', '<=', $to));
    }
}
