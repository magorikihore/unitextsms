<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrafficController extends Controller
{
    public function index(Request $request): View
    {
        $messages = Message::query()
            ->with(['client', 'campaign'])
            ->when($request->queue, fn ($q, $queue) => $q->where('queue_name', $queue))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(40)
            ->withQueryString();

        return view('admin.traffic.index', [
            'messages' => $messages,
            'queues' => [
                'sms-otp' => Message::query()->where('queue_name', 'sms-otp')->where('status', 'queued')->count(),
                'sms-transactional' => Message::query()->where('queue_name', 'sms-transactional')->where('status', 'queued')->count(),
                'sms-alerts' => Message::query()->where('queue_name', 'sms-alerts')->where('status', 'queued')->count(),
                'sms-bulk' => Message::query()->where('queue_name', 'sms-bulk')->where('status', 'queued')->count(),
            ],
        ]);
    }
}
