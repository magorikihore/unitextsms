<?php

namespace App\Services\Unitxt;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UnitxtLegacyReader
{
    public function __construct(private readonly string $connection = 'unitxt_legacy') {}

    public function assertReady(): void
    {
        try {
            DB::connection($this->connection)->getPdo();
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Cannot connect to unitxt_legacy DB. Configure UNITXT_LEGACY_DB_* or use --file=export.json. '.$e->getMessage()
            );
        }
    }

    /**
     * @param  array<int, string>  $emails
     * @param  array<int, int>  $ids
     * @return Collection<int, object>
     */
    public function customers(int $limit = 0, array $emails = [], array $ids = [], bool $activeOnly = true): Collection
    {
        $query = DB::connection($this->connection)
            ->table('users as u')
            ->join('customers as c', 'c.user_id', '=', 'u.id')
            ->select([
                'u.id as user_id',
                'u.uid as user_uid',
                'u.email',
                'u.password',
                'u.first_name',
                'u.last_name',
                'u.status as user_status',
                'u.sms_unit',
                'u.api_token',
                'u.timezone',
                'u.locale',
                'u.created_at as user_created_at',
                'c.id as customer_id',
                'c.uid as customer_uid',
                'c.company',
                'c.website',
                'c.address',
                'c.city',
                'c.postcode',
                'c.state',
                'c.country',
                'c.phone',
                'c.tax_number',
            ])
            ->orderBy('u.id');

        if ($activeOnly) {
            $query->where('u.status', 1)->where('u.is_customer', 1);
        }

        if ($emails !== []) {
            $query->whereIn('u.email', $emails);
        }

        if ($ids !== []) {
            $query->whereIn('u.id', $ids);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function senderIds(int $userId): Collection
    {
        return DB::connection($this->connection)
            ->table('senderid')
            ->where('user_id', $userId)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function contacts(int $userId, int $limit = 0): Collection
    {
        $query = DB::connection($this->connection)
            ->table('contacts')
            ->where('customer_id', $userId)
            ->orderBy('id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, object>  $customers
     * @return array{exported_at: string, source: string, clients: list<array<string, mixed>>}
     */
    public function exportPayload(Collection $customers, bool $withContacts = false, int $contactLimit = 500): array
    {
        $clients = [];

        foreach ($customers as $row) {
            $clients[] = [
                'user' => [
                    'id' => (int) $row->user_id,
                    'uid' => $row->user_uid,
                    'email' => $row->email,
                    'password' => $row->password,
                    'first_name' => $row->first_name,
                    'last_name' => $row->last_name,
                    'status' => (int) $row->user_status,
                    'sms_unit' => $row->sms_unit,
                    'timezone' => $row->timezone,
                    'created_at' => $row->user_created_at,
                ],
                'customer' => [
                    'id' => (int) $row->customer_id,
                    'uid' => $row->customer_uid,
                    'company' => $row->company,
                    'website' => $row->website,
                    'address' => $row->address,
                    'city' => $row->city,
                    'postcode' => $row->postcode,
                    'state' => $row->state,
                    'country' => $row->country,
                    'phone' => $row->phone,
                    'tax_number' => $row->tax_number,
                ],
                'sender_ids' => $this->senderIds((int) $row->user_id)->map(fn ($s) => (array) $s)->values()->all(),
                'contacts' => $withContacts
                    ? $this->contacts((int) $row->user_id, $contactLimit)->map(fn ($c) => (array) $c)->values()->all()
                    : [],
            ];
        }

        return [
            'exported_at' => now()->toIso8601String(),
            'source' => 'unitxt',
            'clients' => $clients,
        ];
    }
}
