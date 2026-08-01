<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Message;
use App\Services\SmsDispatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignController extends Controller
{
    public function __construct(private readonly SmsDispatchService $dispatch) {}

    public function index(Client $client): View
    {
        return view('client.campaigns.index', [
            'client' => $client,
            'campaigns' => Campaign::query()->where('client_id', $client->id)->latest()->paginate(20),
        ]);
    }

    public function show(Client $client, Campaign $campaign): View
    {
        abort_unless($campaign->client_id === $client->id, 404);

        return view('client.campaigns.show', [
            'client' => $client,
            'campaign' => $campaign->load('senderId'),
            'messages' => Message::query()->where('campaign_id', $campaign->id)->latest()->paginate(50),
        ]);
    }

    public function pause(Request $request, Client $client, Campaign $campaign): RedirectResponse
    {
        abort_unless($campaign->client_id === $client->id, 404);
        $this->dispatch->pauseCampaign($campaign, $request->user());

        return back()->with('success', 'Campaign paused.');
    }

    public function cancel(Request $request, Client $client, Campaign $campaign): RedirectResponse
    {
        abort_unless($campaign->client_id === $client->id, 404);
        $this->dispatch->cancelCampaign($campaign, $request->user());

        return back()->with('success', 'Campaign cancelled.');
    }

    public function duplicate(Request $request, Client $client, Campaign $campaign): RedirectResponse
    {
        abort_unless($campaign->client_id === $client->id, 404);
        $copy = $this->dispatch->duplicateCampaign($campaign, $request->user());

        return redirect()->route('client.campaigns.show', [$client, $copy])->with('success', 'Campaign duplicated.');
    }

    public function export(Client $client, Campaign $campaign): StreamedResponse
    {
        abort_unless($campaign->client_id === $client->id, 404);

        return response()->streamDownload(function () use ($campaign) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Phone', 'Status', 'Units', 'Charge', 'Failure', 'Delivered At']);
            Message::query()->where('campaign_id', $campaign->id)->orderBy('id')->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $message) {
                    fputcsv($out, [
                        $message->to_phone_normalized,
                        $message->status?->value,
                        $message->units,
                        $message->client_charge,
                        $message->failure_reason,
                        $message->delivered_at,
                    ]);
                }
            });
            fclose($out);
        }, 'campaign-'.$campaign->id.'.csv', ['Content-Type' => 'text/csv']);
    }
}
