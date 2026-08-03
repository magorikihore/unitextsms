<?php

namespace App\Console\Commands;

use App\Services\Unitxt\UnitxtClientImporter;
use App\Services\Unitxt\UnitxtLegacyReader;
use Illuminate\Console\Command;

class ImportUnitxtClientsCommand extends Command
{
    protected $signature = 'unitxt:import-clients
        {--file= : JSON export path (preferred)}
        {--limit=0 : When reading live legacy DB}
        {--email=* : Filter emails}
        {--id=* : Filter old user ids}
        {--with-contacts : Import contacts}
        {--contact-limit=500 : Max contacts per client}
        {--dry-run : Preview only (default if neither --dry-run nor --execute)}
        {--execute : Persist imports}';

    protected $description = 'Import Unitext clients into UniNText (JSON file or legacy DB connection)';

    public function handle(UnitxtClientImporter $importer): int
    {
        $execute = (bool) $this->option('execute');
        $dryRun = ! $execute;

        if ($dryRun) {
            $this->warn('DRY RUN — no records will be written. Pass --execute to persist.');
        } elseif (! $this->confirm('Import clients into the LIVE database?', true)) {
            $this->warn('Cancelled.');

            return self::SUCCESS;
        }

        $payload = $this->loadPayload();
        if ($payload === null) {
            return self::FAILURE;
        }

        $summary = $importer->import(
            $payload,
            dryRun: $dryRun,
            withContacts: (bool) $this->option('with-contacts'),
            contactLimit: (int) $this->option('contact-limit'),
        );

        $this->table(
            ['Status', 'Email', 'Company/Old ID', 'Balance', 'Senders', 'Contacts', 'Notes'],
            collect($summary['rows'])->map(function (array $row) {
                return [
                    $row['status'] ?? '',
                    $row['email'] ?? '',
                    ($row['company'] ?? '').' / #'.($row['old_user_id'] ?? ($row['client_id'] ?? '')),
                    $row['balance'] ?? ($row['balance_imported'] ?? ''),
                    $row['sender_ids'] ?? ($row['sender_ids_imported'] ?? ''),
                    $row['contacts'] ?? ($row['contacts_imported'] ?? ''),
                    $row['reason'] ?? ($row['error'] ?? ($row['unlimited'] ?? false ? 'unlimited' : '')),
                ];
            })->all(),
        );

        $this->info(sprintf(
            'Done. imported=%d skipped=%d failed=%d (mode=%s)',
            $summary['imported'],
            $summary['skipped'],
            $summary['failed'],
            $dryRun ? 'dry-run' : 'execute',
        ));

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{clients?: list<array<string, mixed>>}|null
     */
    protected function loadPayload(): ?array
    {
        $file = $this->option('file');

        if ($file) {
            $path = str_starts_with($file, '/') ? $file : base_path($file);
            if (! is_readable($path)) {
                $this->error('File not readable: '.$path);

                return null;
            }

            $payload = json_decode((string) file_get_contents($path), true);
            if (! is_array($payload)) {
                $this->error('Invalid JSON export.');

                return null;
            }

            $this->info('Loaded '.count($payload['clients'] ?? []).' clients from '.$path);

            return $this->filterPayload($payload);
        }

        $connection = (string) config('unitxt_import.connection', 'unitxt_legacy');
        $reader = new UnitxtLegacyReader($connection);

        try {
            $reader->assertReady();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            $this->line('Tip: export on the Jasmin host, copy JSON here, then run with --file=...');

            return null;
        }

        $customers = $reader->customers(
            limit: (int) $this->option('limit'),
            emails: array_values(array_filter($this->option('email') ?: [])),
            ids: array_map('intval', array_values(array_filter($this->option('id') ?: []))),
        );

        return $reader->exportPayload(
            $customers,
            withContacts: (bool) $this->option('with-contacts'),
            contactLimit: (int) $this->option('contact-limit'),
        );
    }

    /**
     * @param  array{clients?: list<array<string, mixed>>}  $payload
     * @return array{clients?: list<array<string, mixed>>}
     */
    protected function filterPayload(array $payload): array
    {
        $emails = array_map('strtolower', array_values(array_filter($this->option('email') ?: [])));
        $ids = array_map('intval', array_values(array_filter($this->option('id') ?: [])));
        $limit = (int) $this->option('limit');

        $clients = collect($payload['clients'] ?? [])
            ->when($emails !== [], fn ($c) => $c->filter(fn ($item) => in_array(strtolower((string) data_get($item, 'user.email')), $emails, true)))
            ->when($ids !== [], fn ($c) => $c->filter(fn ($item) => in_array((int) data_get($item, 'user.id'), $ids, true)))
            ->when($limit > 0, fn ($c) => $c->take($limit))
            ->values()
            ->all();

        $payload['clients'] = $clients;

        return $payload;
    }
}
