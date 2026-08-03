<?php

namespace App\Console\Commands;

use App\Services\Unitxt\UnitxtLegacyReader;
use Illuminate\Console\Command;

class ExportUnitxtClientsCommand extends Command
{
    protected $signature = 'unitxt:export-clients
        {--file=storage/app/unitxt-export.json : Output JSON path}
        {--limit=0 : Max clients (0 = all matching)}
        {--email=* : Only these emails}
        {--id=* : Only these old user ids}
        {--with-contacts : Include contacts in export}
        {--contact-limit=500 : Max contacts per client}
        {--all-statuses : Include inactive customers too}';

    protected $description = 'Export clients from legacy Unitext DB into a JSON file for import';

    public function handle(): int
    {
        $connection = (string) config('unitxt_import.connection', 'unitxt_legacy');
        $reader = new UnitxtLegacyReader($connection);
        $reader->assertReady();

        $emails = array_values(array_filter($this->option('email') ?: []));
        $ids = array_map('intval', array_values(array_filter($this->option('id') ?: [])));
        $limit = (int) $this->option('limit');

        $customers = $reader->customers(
            limit: $limit,
            emails: $emails,
            ids: $ids,
            activeOnly: ! $this->option('all-statuses'),
        );

        if ($customers->isEmpty()) {
            $this->warn('No customers matched.');

            return self::FAILURE;
        }

        $payload = $reader->exportPayload(
            $customers,
            withContacts: (bool) $this->option('with-contacts'),
            contactLimit: (int) $this->option('contact-limit'),
        );

        $path = $this->option('file');
        if (! str_starts_with($path, '/')) {
            $path = base_path($path);
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info('Exported '.$customers->count().' clients to '.$path);

        return self::SUCCESS;
    }
}
