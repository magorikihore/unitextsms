<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\SmsProvider;
use Illuminate\Console\Command;

class FixClientDefaultProvidersCommand extends Command
{
    protected $signature = 'sms:fix-default-providers
        {--driver=jasmin : Active provider driver to assign}
        {--deactivate-log : Deactivate log providers}';

    protected $description = 'Point all clients at the active SMS provider and optionally disable log gateways';

    public function handle(): int
    {
        $driver = (string) $this->option('driver');

        $provider = SmsProvider::query()
            ->where('driver', $driver)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $provider) {
            $this->error("No active provider found for driver [{$driver}].");

            return self::FAILURE;
        }

        $updated = Client::query()
            ->where(function ($q) use ($provider) {
                $q->whereNull('default_provider_id')
                    ->orWhere('default_provider_id', '!=', $provider->id);
            })
            ->update(['default_provider_id' => $provider->id]);

        $this->info("Assigned provider #{$provider->id} ({$provider->name}) to {$updated} client(s).");

        if ($this->option('deactivate-log')) {
            $deactivated = SmsProvider::query()
                ->where('driver', 'log')
                ->where('is_active', true)
                ->update(['is_active' => false, 'health_status' => 'disabled']);

            $this->info("Deactivated {$deactivated} log provider(s).");
        }

        return self::SUCCESS;
    }
}
