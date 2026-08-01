<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Contact;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ContactImportService
{
    public function __construct(
        private readonly PhoneNormalizer $phoneNormalizer,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function import(Client $client, User $user, UploadedFile $file): ImportBatch
    {
        $rows = Excel::toArray(null, $file)[0] ?? [];
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), array_shift($rows) ?? []);

        $phoneIdx = $this->findIndex($header, ['phone', 'msisdn', 'mobile', 'number']);
        $firstIdx = $this->findIndex($header, ['first_name', 'firstname', 'name']);
        $lastIdx = $this->findIndex($header, ['last_name', 'lastname', 'surname']);
        $emailIdx = $this->findIndex($header, ['email']);

        if ($phoneIdx === null) {
            throw new \RuntimeException('Spreadsheet must include a phone column.');
        }

        $imported = 0;
        $duplicates = 0;
        $invalid = 0;

        DB::transaction(function () use ($client, $rows, $phoneIdx, $firstIdx, $lastIdx, $emailIdx, &$imported, &$duplicates, &$invalid) {
            foreach ($rows as $row) {
                if (! is_array($row) || count(array_filter($row, fn ($v) => $v !== null && $v !== '')) === 0) {
                    continue;
                }

                $rawPhone = (string) ($row[$phoneIdx] ?? '');
                $normalized = $this->phoneNormalizer->normalize($rawPhone);

                if (! $normalized) {
                    $invalid++;
                    continue;
                }

                $existing = Contact::query()
                    ->where('client_id', $client->id)
                    ->where('phone_normalized', $normalized)
                    ->first();

                if ($existing) {
                    $duplicates++;
                    continue;
                }

                Contact::query()->create([
                    'client_id' => $client->id,
                    'phone' => $rawPhone,
                    'phone_normalized' => $normalized,
                    'first_name' => $firstIdx !== null ? ($row[$firstIdx] ?? null) : null,
                    'last_name' => $lastIdx !== null ? ($row[$lastIdx] ?? null) : null,
                    'email' => $emailIdx !== null ? ($row[$emailIdx] ?? null) : null,
                    'consent_status' => 'imported',
                    'is_valid' => true,
                ]);
                $imported++;
            }
        });

        $batch = ImportBatch::query()->create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'filename' => $file->getClientOriginalName(),
            'type' => 'contacts',
            'total_rows' => count($rows),
            'imported' => $imported,
            'duplicates' => $duplicates,
            'invalid' => $invalid,
            'status' => 'completed',
            'summary' => compact('imported', 'duplicates', 'invalid'),
        ]);

        $this->auditLogger->log('contacts.imported', $batch, null, $batch->toArray(), $user, $client->id);

        return $batch;
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, string>  $candidates
     */
    protected function findIndex(array $header, array $candidates): ?int
    {
        foreach ($header as $i => $col) {
            if (in_array($col, $candidates, true)) {
                return $i;
            }
        }

        return null;
    }
}
