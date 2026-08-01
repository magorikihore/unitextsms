<?php

namespace App\Jobs;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Services\SmsDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class LaunchScheduledCampaigns implements ShouldQueue
{
    use Queueable;

    public function handle(SmsDispatchService $dispatch): void
    {
        $campaigns = Campaign::query()
            ->where('status', CampaignStatus::Scheduled)
            ->where('scheduled_at', '<=', now())
            ->limit(20)
            ->get();

        foreach ($campaigns as $campaign) {
            try {
                $numbers = $campaign->source_meta['valid_numbers'] ?? [];

                if ($numbers === []) {
                    // Recover recipients from any already-created draft messages, else fail safely
                    $campaign->update([
                        'status' => CampaignStatus::Failed,
                        'failure_reason' => 'No recipients stored for scheduled launch.',
                        'completed_at' => now(),
                    ]);
                    continue;
                }

                $dispatch->launchCampaign($campaign, $numbers, $campaign->creator);
            } catch (\Throwable $e) {
                Log::error('Scheduled campaign launch failed', [
                    'campaign_id' => $campaign->id,
                    'error' => $e->getMessage(),
                ]);
                $campaign->update([
                    'status' => CampaignStatus::Failed,
                    'failure_reason' => $e->getMessage(),
                    'completed_at' => now(),
                ]);
            }
        }
    }
}
