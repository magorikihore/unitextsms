<?php

namespace App\Services\Unitxt;

use App\Enums\ClientRole;
use App\Enums\ClientStatus;
use App\Enums\SenderIdStatus;
use App\Models\Client;
use App\Models\Contact;
use App\Models\SenderId;
use App\Models\SmsProvider;
use App\Models\UnitxtImportMap;
use App\Models\User;
use App\Services\CreditLedgerService;
use App\Services\PhoneNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UnitxtClientImporter
{
    public function __construct(
        private readonly CreditLedgerService $ledger,
        private readonly PhoneNormalizer $phones,
    ) {}

    /**
     * @param  array{clients?: list<array<string, mixed>>}  $payload
     * @return array{imported: int, skipped: int, failed: int, rows: list<array<string, mixed>>}
     */
    public function import(array $payload, bool $dryRun = true, bool $withContacts = false, int $contactLimit = 500): array
    {
        $summary = ['imported' => 0, 'skipped' => 0, 'failed' => 0, 'rows' => []];
        $source = config('unitxt_import.source', 'unitxt');

        foreach ($payload['clients'] ?? [] as $item) {
            try {
                $result = $this->importOne($item, $source, $dryRun, $withContacts, $contactLimit);
                $summary['rows'][] = $result;
                if (in_array($result['status'], ['imported', 'dry-run'], true)) {
                    $summary['imported']++;
                } else {
                    $summary['skipped']++;
                }
            } catch (\Throwable $e) {
                $summary['failed']++;
                $summary['rows'][] = [
                    'status' => 'failed',
                    'email' => data_get($item, 'user.email'),
                    'old_user_id' => data_get($item, 'user.id'),
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function importOne(array $item, string $source, bool $dryRun, bool $withContacts, int $contactLimit): array
    {
        $userData = $item['user'] ?? [];
        $customer = $item['customer'] ?? [];
        $email = strtolower(trim((string) ($userData['email'] ?? '')));
        $oldUserId = (int) ($userData['id'] ?? 0);

        if ($email === '' || $oldUserId <= 0) {
            throw new \InvalidArgumentException('Missing email or old user id.');
        }

        $existingMap = UnitxtImportMap::query()
            ->where('source', $source)
            ->where('old_user_id', $oldUserId)
            ->first();

        if ($existingMap) {
            return [
                'status' => 'skipped',
                'reason' => 'already_imported',
                'email' => $email,
                'old_user_id' => $oldUserId,
                'client_id' => $existingMap->client_id,
            ];
        }

        $balance = $this->resolveBalance($userData['sms_unit'] ?? null);
        $personName = trim(($userData['first_name'] ?? '').' '.($userData['last_name'] ?? ''));
        $company = trim((string) ($customer['company'] ?? '')) ?: ($personName !== '' ? $personName : $email);
        $senderIds = $item['sender_ids'] ?? [];
        $contacts = $withContacts ? array_slice($item['contacts'] ?? [], 0, $contactLimit) : [];

        $preview = [
            'status' => $dryRun ? 'dry-run' : 'imported',
            'email' => $email,
            'old_user_id' => $oldUserId,
            'company' => $company,
            'balance' => $balance,
            'unlimited' => (string) ($userData['sms_unit'] ?? '') === '-1',
            'sender_ids' => count($senderIds),
            'contacts' => count($contacts),
        ];

        if ($dryRun) {
            return $preview;
        }

        return DB::transaction(function () use ($source, $userData, $customer, $email, $oldUserId, $balance, $company, $personName, $senderIds, $contacts, $preview) {
            $owner = User::query()->where('email', $email)->first();

            if ($owner && $owner->isPlatformUser()) {
                throw new \RuntimeException("Email {$email} belongs to a platform admin; skip or rename first.");
            }

            if (! $owner) {
                $tempPassword = Str::password(32);
                $owner = User::query()->create([
                    'name' => $personName !== '' ? $personName : $company,
                    'email' => $email,
                    'password' => $tempPassword,
                    'email_verified_at' => now(),
                    'is_active' => ((int) ($userData['status'] ?? 1)) === 1,
                    'platform_role' => null,
                ]);

                // Write legacy hash directly so bcrypt cost differences do not fail Hash::verifyConfiguration.
                if (! empty($userData['password']) && str_starts_with((string) $userData['password'], '$')) {
                    DB::table('users')->where('id', $owner->id)->update([
                        'password' => $userData['password'],
                    ]);
                    $owner->refresh();
                }
            }

            $client = Client::query()->create([
                'company_name' => Str::limit($company, 255, ''),
                'trading_name' => $customer['company'] ?? null,
                'registration_number' => $customer['tax_number'] ?? null,
                'contact_name' => $personName !== '' ? $personName : $company,
                'contact_email' => $email,
                'contact_phone' => $customer['phone'] ?? null,
                'address' => $customer['address'] ?? null,
                'city' => $customer['city'] ?? null,
                'country' => $this->mapCountry($customer['country'] ?? null),
                'status' => ((int) ($userData['status'] ?? 1)) === 1 ? ClientStatus::Active : ClientStatus::Suspended,
                'billing_type' => 'prepaid',
                'sms_balance' => 0,
                'credit_limit' => 0,
                'price_per_sms' => (float) config('unitxt_import.default_price_per_sms', 0.05),
                'low_balance_threshold' => 50,
                'allowed_countries' => ['TZ'],
                'can_send_otp' => true,
                'can_send_transactional' => true,
                'can_send_marketing' => true,
                'api_enabled' => true,
                'default_provider_id' => $this->defaultProviderId(),
                'notes' => $this->buildNotes($userData, $customer),
                'approved_at' => now(),
            ]);

            $client->users()->syncWithoutDetaching([
                $owner->id => ['role' => ClientRole::Owner->value, 'is_active' => true],
            ]);

            $senderCount = 0;
            foreach ($senderIds as $sid) {
                $sender = trim((string) ($sid['sender_id'] ?? ''));
                if ($sender === '') {
                    continue;
                }

                SenderId::query()->firstOrCreate(
                    ['client_id' => $client->id, 'sender' => $sender],
                    [
                        'status' => $this->mapSenderStatus((string) ($sid['status'] ?? 'pending')),
                        'expires_at' => $sid['validity_date'] ?? null,
                        'approved_at' => (($sid['status'] ?? '') === 'active') ? now() : null,
                        'rejection_reason' => ($sid['status'] ?? '') === 'block' ? 'Imported as blocked from Unitext' : null,
                    ],
                );
                $senderCount++;
            }

            $contactCount = 0;
            foreach ($contacts as $contact) {
                $rawPhone = (string) ($contact['phone'] ?? '');
                $normalized = $this->phones->normalize($rawPhone);
                if (! $normalized) {
                    continue;
                }

                Contact::query()->firstOrCreate(
                    ['client_id' => $client->id, 'phone_normalized' => $normalized],
                    [
                        'phone' => $rawPhone ?: $normalized,
                        'first_name' => $contact['first_name'] ?? null,
                        'last_name' => $contact['last_name'] ?? null,
                        'email' => $contact['email'] ?? null,
                        'is_opted_out' => false,
                        'is_valid' => true,
                        'consent_status' => 'unknown',
                    ],
                );
                $contactCount++;
            }

            if ($balance > 0) {
                $this->ledger->credit(
                    $client,
                    $balance,
                    'Migrated SMS balance from Unitext (user #'.$oldUserId.')',
                    $owner,
                    null,
                    ['source' => $source, 'old_user_id' => $oldUserId, 'old_sms_unit' => $userData['sms_unit'] ?? null],
                );
            }

            UnitxtImportMap::query()->create([
                'source' => $source,
                'old_user_id' => $oldUserId,
                'old_customer_id' => (int) ($customer['id'] ?? 0) ?: null,
                'old_email' => $email,
                'client_id' => $client->id,
                'user_id' => $owner->id,
                'sender_ids_imported' => $senderCount,
                'contacts_imported' => $contactCount,
                'balance_imported' => $balance,
                'meta' => [
                    'old_user_uid' => $userData['uid'] ?? null,
                    'api_token_not_migrated' => true,
                    'unlimited' => (string) ($userData['sms_unit'] ?? '') === '-1',
                ],
                'imported_at' => now(),
            ]);

            return $preview + [
                'status' => 'imported',
                'client_id' => $client->id,
                'user_id' => $owner->id,
                'sender_ids_imported' => $senderCount,
                'contacts_imported' => $contactCount,
            ];
        });
    }

    protected function resolveBalance(mixed $smsUnit): float
    {
        if ($smsUnit === null || $smsUnit === '') {
            return 0;
        }

        if ((string) $smsUnit === '-1') {
            return (float) config('unitxt_import.unlimited_balance_as', 0);
        }

        return max(0, (float) $smsUnit);
    }

    protected function mapSenderStatus(string $status): SenderIdStatus
    {
        return match ($status) {
            'active' => SenderIdStatus::Approved,
            'block' => SenderIdStatus::Suspended,
            'expired' => SenderIdStatus::Expired,
            'payment_required' => SenderIdStatus::Pending,
            default => SenderIdStatus::Pending,
        };
    }

    protected function mapCountry(?string $country): string
    {
        $country = strtolower(trim((string) $country));

        return match (true) {
            $country === '' => 'TZ',
            str_contains($country, 'tanzania'), str_contains($country, 'tz') => 'TZ',
            default => Str::upper(Str::limit($country, 8, '')),
        };
    }

    /**
     * @param  array<string, mixed>  $userData
     * @param  array<string, mixed>  $customer
     */
    protected function buildNotes(array $userData, array $customer): string
    {
        $bits = [
            'Imported from Unitext',
            'old_user_id='.($userData['id'] ?? ''),
            'old_sms_unit='.($userData['sms_unit'] ?? ''),
        ];

        if ((string) ($userData['sms_unit'] ?? '') === '-1') {
            $bits[] = 'HAD_UNLIMITED_BALANCE — review pricing/plan manually';
        }

        if (! empty($customer['website'])) {
            $bits[] = 'website='.$customer['website'];
        }

        $bits[] = 'API token not migrated — issue a new key in portal';

        return implode(' | ', $bits);
    }

    protected function defaultProviderId(): ?int
    {
        $configured = config('unitxt_import.default_provider_id');
        if ($configured) {
            return (int) $configured;
        }

        return SmsProvider::query()->where('is_active', true)->value('id');
    }
}
