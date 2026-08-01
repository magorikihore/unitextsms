<?php

namespace App\Http\Controllers\Client;

use App\Enums\CampaignStatus;
use App\Enums\MessageStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Message;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Client $client): View
    {
        $today = now()->startOfDay();
        $month = now()->startOfMonth();

        $base = Message::query()->where('client_id', $client->id);

        return view('client.dashboard', [
            'client' => $client,
            'balance' => $client->sms_balance,
            'lowBalance' => (float) $client->sms_balance <= (float) $client->low_balance_threshold,
            'sentToday' => (clone $base)->where('created_at', '>=', $today)->count(),
            'sentMonth' => (clone $base)->where('created_at', '>=', $month)->count(),
            'delivered' => (clone $base)->where('created_at', '>=', $month)->where('status', MessageStatus::Delivered)->count(),
            'failed' => (clone $base)->where('created_at', '>=', $month)->where('status', MessageStatus::Failed)->count(),
            'pending' => (clone $base)->whereIn('status', [MessageStatus::Queued, MessageStatus::Submitted, MessageStatus::Pending])->count(),
            'expired' => (clone $base)->where('created_at', '>=', $month)->where('status', MessageStatus::Expired)->count(),
            'deliveryRate' => $this->rate($client),
            'recentCampaigns' => Campaign::query()->where('client_id', $client->id)->latest()->limit(5)->get(),
            'scheduledCampaigns' => Campaign::query()
                ->where('client_id', $client->id)
                ->where('status', CampaignStatus::Scheduled)
                ->orderBy('scheduled_at')
                ->limit(5)
                ->get(),
            'otpAvgMs' => (int) Message::query()
                ->where('client_id', $client->id)
                ->where('category', 'otp')
                ->whereNotNull('delivery_ms')
                ->where('created_at', '>=', $today)
                ->avg('delivery_ms'),
            'activity' => AuditLog::query()
                ->where('client_id', $client->id)
                ->latest()
                ->limit(8)
                ->get(),
            'chart' => $this->chart($client),
        ]);
    }

    protected function rate(Client $client): float
    {
        $sent = Message::query()->where('client_id', $client->id)->where('created_at', '>=', now()->startOfMonth())->count();
        $delivered = Message::query()->where('client_id', $client->id)->where('created_at', '>=', now()->startOfMonth())->where('status', MessageStatus::Delivered)->count();

        return $sent > 0 ? round(($delivered / $sent) * 100, 1) : 0;
    }

    protected function chart(Client $client): array
    {
        $days = collect(range(6, 0))->map(function ($ago) use ($client) {
            $day = now()->subDays($ago)->startOfDay();

            return [
                'label' => $day->format('D'),
                'delivered' => Message::query()
                    ->where('client_id', $client->id)
                    ->whereDate('created_at', $day)
                    ->where('status', MessageStatus::Delivered)
                    ->count(),
                'failed' => Message::query()
                    ->where('client_id', $client->id)
                    ->whereDate('created_at', $day)
                    ->where('status', MessageStatus::Failed)
                    ->count(),
            ];
        });

        return $days->all();
    }
}
