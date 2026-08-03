<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\SystemAlert;
use Illuminate\View\View;

class AlertController extends Controller
{
    public function index(Client $client): View
    {
        $alerts = SystemAlert::query()
            ->where(function ($q) use ($client) {
                $q->whereNull('client_id')->orWhere('client_id', $client->id);
            })
            ->where('is_resolved', false)
            ->latest()
            ->paginate(30);

        return view('client.alerts.index', [
            'client' => $client,
            'alerts' => $alerts,
        ]);
    }
}
