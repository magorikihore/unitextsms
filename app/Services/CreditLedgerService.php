<?php

namespace App\Services;

use App\Enums\LedgerEntryType;
use App\Models\Client;
use App\Models\CreditLedgerEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class CreditLedgerService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function credit(
        Client $client,
        float $amount,
        string $reason,
        ?User $actor = null,
        ?Model $related = null,
        array $meta = [],
    ): CreditLedgerEntry {
        return $this->record($client, LedgerEntryType::Credit, abs($amount), $reason, $actor, $related, $meta);
    }

    public function debit(
        Client $client,
        float $amount,
        string $reason,
        ?User $actor = null,
        ?Model $related = null,
        array $meta = [],
    ): CreditLedgerEntry {
        return $this->record($client, LedgerEntryType::Debit, abs($amount), $reason, $actor, $related, $meta);
    }

    public function adjust(
        Client $client,
        float $signedAmount,
        string $reason,
        ?User $actor = null,
        array $meta = [],
    ): CreditLedgerEntry {
        $type = $signedAmount >= 0 ? LedgerEntryType::Adjustment : LedgerEntryType::Debit;

        return $this->record($client, $type, abs($signedAmount), $reason, $actor, null, $meta, allowNegative: true);
    }

    protected function record(
        Client $client,
        LedgerEntryType $type,
        float $amount,
        string $reason,
        ?User $actor,
        ?Model $related,
        array $meta,
        bool $allowNegative = false,
    ): CreditLedgerEntry {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Ledger amount must be greater than zero.');
        }

        return DB::transaction(function () use ($client, $type, $amount, $reason, $actor, $related, $meta, $allowNegative) {
            /** @var Client $locked */
            $locked = Client::query()->whereKey($client->id)->lockForUpdate()->firstOrFail();

            $before = (float) $locked->sms_balance;
            $delta = in_array($type, [LedgerEntryType::Credit, LedgerEntryType::Refund], true)
                ? $amount
                : ($type === LedgerEntryType::Adjustment ? $amount : -$amount);

            // Adjustment with positive amount already handled; for debit/refund clarity:
            if ($type === LedgerEntryType::Debit) {
                $delta = -$amount;
            } elseif ($type === LedgerEntryType::Credit || $type === LedgerEntryType::Refund) {
                $delta = $amount;
            }

            $after = round($before + $delta, 2);

            if (! $allowNegative && $after < 0 && $locked->billing_type->value === 'prepaid') {
                throw new RuntimeException('Insufficient SMS credit balance.');
            }

            if ($locked->billing_type->value === 'postpaid' && $after < -1 * (float) $locked->credit_limit) {
                throw new RuntimeException('Credit limit exceeded.');
            }

            $locked->sms_balance = $after;
            $locked->save();

            $entry = CreditLedgerEntry::query()->create([
                'client_id' => $locked->id,
                'type' => $type,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'reference' => 'LED-'.strtoupper(Str::random(12)),
                'reason' => $reason,
                'related_type' => $related ? $related::class : null,
                'related_id' => $related?->getKey(),
                'created_by' => $actor?->id,
                'meta' => $meta,
            ]);

            $this->auditLogger->log(
                action: 'credit.ledger.'.$type->value,
                auditable: $entry,
                new: $entry->toArray(),
                user: $actor,
                clientId: $locked->id,
            );

            $client->sms_balance = $locked->sms_balance;

            return $entry;
        });
    }
}
