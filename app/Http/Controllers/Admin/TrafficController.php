<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Message;
use App\Services\JasminGatewayHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class TrafficController extends Controller
{
    public function index(Request $request): View
    {
        $phone = preg_replace('/\s+/', '', (string) $request->input('phone', '')) ?: null;

        $messages = Message::query()
            ->with(['client', 'campaign'])
            ->when($request->queue, fn ($q, $queue) => $q->where('queue_name', $queue))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->client_id, fn ($q, $clientId) => $q->where('client_id', $clientId))
            ->when($phone, function ($q) use ($phone) {
                $digits = preg_replace('/\D+/', '', $phone) ?: $phone;
                $q->where(function ($inner) use ($phone, $digits) {
                    $inner->where('to_phone_normalized', 'like', "%{$digits}%")
                        ->orWhere('to_phone', 'like', "%{$phone}%")
                        ->orWhere('to_phone', 'like', "%{$digits}%");
                });
            })
            ->latest()
            ->paginate(40)
            ->withQueryString();

        return view('admin.traffic.index', [
            'messages' => $messages,
            'clients' => Client::query()->orderBy('company_name')->get(['id', 'company_name']),
            'filters' => [
                'phone' => $request->input('phone', ''),
                'queue' => $request->input('queue', ''),
                'status' => $request->input('status', ''),
                'client_id' => $request->input('client_id', ''),
            ],
            'queues' => [
                'sms-otp' => Message::query()->where('queue_name', 'sms-otp')->where('status', 'queued')->count(),
                'sms-transactional' => Message::query()->where('queue_name', 'sms-transactional')->where('status', 'queued')->count(),
                'sms-bulk' => Message::query()->where('queue_name', 'sms-bulk')->where('status', 'queued')->count(),
            ],
            'rateLimits' => config('sms.rate_limits'),
            'bulkPaused' => Cache::get('sms:bulk:paused'),
            'otpLatencyMs' => (int) Cache::get('sms:otp:avg_latency_ms', 0),
            'gatewayHealth' => app(JasminGatewayHealth::class)->snapshot(),
        ]);
    }
}
