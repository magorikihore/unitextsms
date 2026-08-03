<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Deposit;
use App\Services\PayIn\DepositService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class DepositController extends Controller
{
    public function store(Request $request, Client $client, DepositService $deposits): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'amount' => ['required', 'numeric', 'min:'.(int) config('payin.min_amount', 100)],
        ]);

        try {
            $deposit = $deposits->start($client, $request->user(), $data['phone'], (float) $data['amount']);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json($this->payload($deposit, $client->fresh()));
    }

    public function status(Client $client, Deposit $deposit, DepositService $deposits): JsonResponse
    {
        abort_unless($deposit->client_id === $client->id, 404);

        $deposit = $deposits->refresh($deposit);

        return response()->json($this->payload($deposit, $client->fresh()));
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(Deposit $deposit, Client $client): array
    {
        return [
            'deposit' => [
                'uuid' => $deposit->uuid,
                'reference' => $deposit->reference,
                'request_ref' => $deposit->request_ref,
                'phone' => $deposit->phone,
                'amount' => (float) $deposit->amount,
                'currency' => $deposit->currency,
                'credits' => $deposit->credits !== null ? (float) $deposit->credits : null,
                'status' => $deposit->status,
                'failure_reason' => $deposit->failure_reason,
                'is_final' => $deposit->isFinal(),
                'is_completed' => $deposit->isCompleted(),
            ],
            'balance' => (float) $client->sms_balance,
            'poll_interval_seconds' => (int) config('payin.poll_interval_seconds', 5),
            'success_dismiss_seconds' => (int) config('payin.success_dismiss_seconds', 60),
        ];
    }
}
