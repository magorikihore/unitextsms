<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ClientStatus;
use App\Enums\MessageCategory;
use App\Enums\MessageStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\CreditLedgerEntry;
use App\Models\Message;
use App\Models\SmsProvider;
use App\Models\SystemAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = now()->startOfDay();
        $month = now()->startOfMonth();

        $messagesToday = Message::query()->where('created_at', '>=', $today);
        $messagesMonth = Message::query()->where('created_at', '>=', $month);

        $delivered = (clone $messagesMonth)->where('status', MessageStatus::Delivered)->count();
        $submitted = (clone $messagesMonth)->whereIn('status', [
            MessageStatus::Submitted, MessageStatus::Sent, MessageStatus::Delivered, MessageStatus::Failed,
        ])->count();

        $revenue = (float) Message::query()->where('created_at', '>=', $month)->sum('client_charge');
        $cost = (float) Message::query()->where('created_at', '>=', $month)->sum('provider_cost');

        return view('admin.dashboard', [
            'totalClients' => Client::query()->count(),
            'activeClients' => Client::query()->where('status', ClientStatus::Active)->count(),
            'suspendedClients' => Client::query()->where('status', ClientStatus::Suspended)->count(),
            'smsToday' => (clone $messagesToday)->count(),
            'smsMonth' => (clone $messagesMonth)->count(),
            'delivered' => (clone $messagesToday)->where('status', MessageStatus::Delivered)->count(),
            'pending' => (clone $messagesToday)->whereIn('status', [MessageStatus::Queued, MessageStatus::Pending, MessageStatus::Submitted])->count(),
            'failed' => (clone $messagesToday)->where('status', MessageStatus::Failed)->count(),
            'deliveryRate' => $submitted > 0 ? round(($delivered / $submitted) * 100, 1) : 0,
            'queueSize' => Message::query()->where('status', MessageStatus::Queued)->count(),
            'otpAvgMs' => (int) Message::query()
                ->where('category', MessageCategory::Otp)
                ->whereNotNull('delivery_ms')
                ->where('created_at', '>=', $today)
                ->avg('delivery_ms'),
            'providerBalance' => (float) SmsProvider::query()->sum('balance'),
            'clientBalances' => (float) Client::query()->sum('sms_balance'),
            'revenue' => $revenue,
            'providerCost' => $cost,
            'profit' => $revenue - $cost,
            'failedCampaigns' => Campaign::query()->where('status', 'failed')->latest()->limit(5)->get(),
            'alerts' => SystemAlert::query()->where('is_resolved', false)->latest()->limit(8)->get(),
            'topClients' => Client::query()
                ->orderByDesc('sms_balance')
                ->limit(5)
                ->get(['id', 'company_name', 'sms_balance', 'status']),
            'creditsToday' => CreditLedgerEntry::query()->where('created_at', '>=', $today)->count(),
            'trafficByCategory' => Message::query()
                ->select('category', DB::raw('count(*) as total'))
                ->where('created_at', '>=', $month)
                ->groupBy('category')
                ->pluck('total', 'category'),
        ]);
    }
}
