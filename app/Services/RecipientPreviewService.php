<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Suppression;

class RecipientPreviewService
{
    public function __construct(
        private readonly PhoneNormalizer $phoneNormalizer,
        private readonly SmsCounter $smsCounter,
    ) {}

    /**
     * @param  array<int, string>  $rawNumbers
     * @return array{
     *   valid: array<int, string>,
     *   invalid: array<int, string>,
     *   duplicates_removed: int,
     *   opted_out: array<int, string>,
     *   estimated_units: int,
     *   estimated_cost: float,
     *   remaining_balance: float,
     *   estimated_completion_seconds: int
     * }
     */
    public function preview(Client $client, array $rawNumbers, string $messageBody, int $ratePerSecond = 18): array
    {
        $seen = [];
        $valid = [];
        $invalid = [];
        $duplicates = 0;
        $optedOut = [];

        $suppressions = Suppression::query()
            ->where(function ($q) use ($client) {
                $q->where('client_id', $client->id)->orWhere('is_global', true);
            })
            ->pluck('phone_normalized')
            ->all();

        $optedOutContacts = Contact::query()
            ->where('client_id', $client->id)
            ->where('is_opted_out', true)
            ->pluck('phone_normalized')
            ->all();

        $blocked = array_unique(array_merge($suppressions, $optedOutContacts));

        foreach ($rawNumbers as $raw) {
            $raw = trim((string) $raw);
            if ($raw === '') {
                continue;
            }

            $normalized = $this->phoneNormalizer->normalize($raw);
            if (! $normalized) {
                $invalid[] = $raw;
                continue;
            }

            if (isset($seen[$normalized])) {
                $duplicates++;
                continue;
            }
            $seen[$normalized] = true;

            if (in_array($normalized, $blocked, true)) {
                $optedOut[] = $normalized;
                continue;
            }

            $valid[] = $normalized;
        }

        $unitsPerMessage = $this->smsCounter->units($messageBody);
        $estimatedUnits = count($valid) * $unitsPerMessage;
        $estimatedCost = round($estimatedUnits * (float) $client->price_per_sms, 2);
        $rate = max(1, $ratePerSecond);

        return [
            'valid' => $valid,
            'invalid' => $invalid,
            'duplicates_removed' => $duplicates,
            'opted_out' => $optedOut,
            'estimated_units' => $estimatedUnits,
            'estimated_cost' => $estimatedCost,
            'remaining_balance' => round($client->availableBalance() - $estimatedCost, 2),
            'estimated_completion_seconds' => (int) ceil(count($valid) / $rate),
            'units_per_message' => $unitsPerMessage,
        ];
    }
}
