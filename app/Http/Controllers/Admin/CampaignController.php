<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Message;
use App\Services\SmsDispatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function __construct(private readonly SmsDispatchService $dispatch) {}

    public function index(Request $request): View
    {
        $campaigns = Campaign::query()
            ->with(['client', 'senderId', 'creator'])
            ->when($request->client_id, fn ($q, $id) => $q->where('client_id', $id))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->category, fn ($q, $category) => $q->where('category', $category))
            ->when($request->q, fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.campaigns.index', [
            'campaigns' => $campaigns,
            'clients' => Client::query()->orderBy('company_name')->get(['id', 'company_name', 'contact_email', 'contact_phone', 'sms_balance']),
            'statuses' => CampaignStatus::cases(),
            'filters' => $request->all(),
        ]);
    }

    public function show(Campaign $campaign): View
    {
        $campaign->load(['client', 'senderId', 'creator', 'template']);

        return view('admin.campaigns.show', [
            'campaign' => $campaign,
            'messages' => Message::query()
                ->where('campaign_id', $campaign->id)
                ->latest()
                ->paginate(50),
        ]);
    }

    public function pause(Request $request, Campaign $campaign): RedirectResponse
    {
        try {
            $this->dispatch->pauseCampaign($campaign, $request->user());
        } catch (\Throwable $e) {
            return $this->ajaxBack($request, $e->getMessage(), ['campaign' => $e->getMessage()]);
        }

        return $this->ajaxBack($request, 'Campaign paused.');
    }

    public function cancel(Request $request, Campaign $campaign): RedirectResponse
    {
        try {
            $this->dispatch->cancelCampaign($campaign, $request->user());
        } catch (\Throwable $e) {
            return $this->ajaxBack($request, $e->getMessage(), ['campaign' => $e->getMessage()]);
        }

        return $this->ajaxBack($request, 'Campaign cancelled.');
    }
}
