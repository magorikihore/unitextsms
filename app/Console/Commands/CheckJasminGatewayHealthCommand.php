<?php

namespace App\Console\Commands;

use App\Services\JasminGatewayHealth;
use Illuminate\Console\Command;

class CheckJasminGatewayHealthCommand extends Command
{
    protected $signature = 'sms:jasmin-health {--fresh : Bypass cache}';

    protected $description = 'Probe Jasmin HTTP health and open/resolve gateway alerts';

    public function handle(JasminGatewayHealth $health): int
    {
        $snapshot = $this->option('fresh') ? $health->check() : $health->snapshot(force: true);

        $this->table(
            ['Field', 'Value'],
            collect($snapshot)->map(fn ($v, $k) => [$k, is_bool($v) ? ($v ? 'true' : 'false') : (string) ($v ?? '—')])->values()->all()
        );

        return $snapshot['healthy'] ? self::SUCCESS : self::FAILURE;
    }
}
