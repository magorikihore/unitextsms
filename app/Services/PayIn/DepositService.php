<?php

namespace App\Services\PayIn;

use App\Enums\PaymentMethod;
use App\Models\Client;
use App\Models\Deposit;
use App\Models\User;
use App\Services\CreditLedgerService;
use App\Services\PhoneNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DepositService
{
    public function __construct(
        private readonly PayInClient $payIn,
        private readonly CreditLedgerService $ledger,
        private readonly PhoneNormalizer $phones,
    ) {}

    public function start(Client $client, User $user, string $phone, float $amount): Deposit
    {
        if (! $this->payIn->enabled()) {
            throw new RuntimeException('Deposits are not available right now.');
        }

        $normalized = $this->phones->normalize($phone);
        if (! $normalized) {
            throw new RuntimeException('Enter a valid Tanzanian mobile number.');
        }

        $min = (int) config('payin.min_amount', 100);
        $max = (int) config('payin.max_amount', 10000000);
        if ($amount < $min || $amount > $max) {
            throw new RuntimeException("Amount must be between {$min} and {$max} TZS.");
        }

        $deposit = Deposit::query()->create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'phone' => $normalized,
            'amount' => $amount,
            'currency' => config('payin.currency', 'TZS'),
            'status' => 'pending',
        ]);

        try {
            $result = $this->payIn->collect([
                'phone' => $normalized,
                'amount' => (int) round($amount),
                'reference' => $deposit->reference,
                'description' => 'UniNText SMS deposit for '.$client->company_name,
                'callback_url' => route('webhooks.payin'),
            ]);
        } catch (\Throwable $e) {
            $deposit->update([
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
                'failed_at' => now(),
            ]);
            throw $e;
        }

        $deposit->update([
            'request_ref' => $result['request_ref'] ?? null,
            'operator_ref' => isset($result['operator_ref']) ? (string) $result['operator_ref'] : null,
            'operator' => $result['operator'] ?? null,
            'status' => $this->normalizeStatus((string) ($result['status'] ?? 'processing')),
            'meta' => ['start_response' => $result],
        ]);

        return $deposit->fresh();
    }

    public function refresh(Deposit $deposit): Deposit
    {
        if ($deposit->isFinal() || ! $deposit->request_ref) {
            return $deposit;
        }

        try {
            $result = $this->payIn->status($deposit->request_ref);
        } catch (\Throwable $e) {
            Log::warning('PayIn status check failed', [
                'deposit' => $deposit->uuid,
                'error' => $e->getMessage(),
            ]);

            return $deposit;
        }

        return $this->applyRemoteStatus($deposit, $result);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function applyWebhook(array $payload): ?Deposit
    {
        $requestRef = (string) ($payload['request_ref'] ?? '');
        if ($requestRef === '') {
            return null;
        }

        $deposit = Deposit::query()->where('request_ref', $requestRef)->first();
        if (! $deposit) {
            $external = (string) ($payload['external_ref'] ?? '');
            if ($external !== '') {
                $deposit = Deposit::query()->where('reference', $external)->first();
            }
        }

        if (! $deposit) {
            return null;
        }

        return $this->applyRemoteStatus($deposit, $payload);
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    protected function applyRemoteStatus(Deposit $deposit, array $remote): Deposit
    {
        $status = $this->normalizeStatus((string) ($remote['status'] ?? $deposit->status));

        return DB::transaction(function () use ($deposit, $remote, $status) {
            /** @var Deposit $locked */
            $locked = Deposit::query()->whereKey($deposit->id)->lockForUpdate()->firstOrFail();

            if ($locked->isCompleted()) {
                return $locked;
            }

            $locked->fill([
                'operator_ref' => isset($remote['operator_ref']) ? (string) $remote['operator_ref'] : $locked->operator_ref,
                'receipt_number' => $remote['receipt_number'] ?? $locked->receipt_number,
                'operator' => $remote['operator'] ?? $locked->operator,
                'meta' => array_merge($locked->meta ?? [], ['last_remote' => $remote]),
            ]);

            if ($status === 'completed') {
                $credits = (float) ($remote['gross_amount'] ?? $remote['amount'] ?? $locked->amount);
                $locked->status = 'completed';
                $locked->credits = $credits;
                $locked->completed_at = now();
                $locked->failure_reason = null;
                $locked->save();

                $this->ledger->credit(
                    $locked->client,
                    $credits,
                    'PayIn deposit '.$locked->reference.($locked->request_ref ? ' / '.$locked->request_ref : ''),
                    $locked->user,
                    $locked,
                    [
                        'request_ref' => $locked->request_ref,
                        'receipt_number' => $locked->receipt_number,
                        'phone' => $locked->phone,
                        'operator' => $locked->operator,
                        'payment_method' => PaymentMethod::Mobile->value,
                    ],
                    PaymentMethod::Mobile,
                );

                return $locked->fresh();
            }

            if (in_array($status, ['failed', 'expired', 'rejected'], true)) {
                $locked->status = $status;
                $locked->failure_reason = (string) ($remote['error'] ?? $remote['message'] ?? 'Payment '.$status);
                $locked->failed_at = now();
                $locked->save();

                return $locked->fresh();
            }

            $locked->status = $status;
            $locked->save();

            return $locked->fresh();
        });
    }

    protected function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));

        return match ($status) {
            'success', 'successful', 'paid' => 'completed',
            'fail', 'cancelled', 'canceled' => 'failed',
            default => $status !== '' ? $status : 'processing',
        };
    }
}
