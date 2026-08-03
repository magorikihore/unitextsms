<?php

namespace App\Services;

use App\Enums\MessageCategory;
use App\Enums\MessageStatus;
use App\Jobs\SendOtpSms;
use App\Models\Client;
use App\Models\Message;
use App\Models\OtpChallenge;
use App\Models\SenderId;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use RuntimeException;

class OtpService
{
    public function __construct(
        private readonly PhoneNormalizer $phoneNormalizer,
        private readonly SmsCounter $smsCounter,
        private readonly MessagePersonalizer $personalizer,
        private readonly CreditLedgerService $ledger,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $vars
     */
    public function request(
        Client $client,
        User $user,
        SenderId $senderId,
        string $to,
        ?string $code = null,
        string $template = 'Your verification code is {{code}}. Valid for {{ttl}} minutes.',
        ?string $purpose = null,
        ?string $ip = null,
        ?string $deviceId = null,
        array $vars = [],
    ): array {
        $normalized = $this->phoneNormalizer->normalize($to);
        if (! $normalized) {
            throw new RuntimeException('Invalid phone number.');
        }

        $this->assertRateLimits($client, $normalized, $ip, $deviceId);

        $code ??= (string) random_int(100000, 999999);
        $ttl = (int) config('sms.otp.ttl_minutes', 5);
        $expiresAt = now()->addMinutes($ttl);

        $body = $this->personalizer->render($template, array_merge($vars, [
            'code' => $code,
            'ttl' => $ttl,
        ]));

        return DB::transaction(function () use ($client, $user, $senderId, $to, $normalized, $code, $body, $purpose, $ip, $deviceId, $expiresAt, $ttl) {
            $this->invalidatePrevious($client, $normalized);

            $units = $this->smsCounter->units($body);
            $charge = round($units * (float) $client->price_per_sms, 4);
            $reference = 'OTP-'.Str::upper(Str::random(10));

            $message = Message::query()->create([
                'client_id' => $client->id,
                'sender_id_id' => $senderId->id,
                'created_by' => $user->id,
                'to_phone' => $to,
                'to_phone_normalized' => $normalized,
                'from_sender' => $senderId->sender,
                'body' => $body,
                'category' => MessageCategory::Otp,
                'status' => MessageStatus::Queued,
                'queue_name' => MessageCategory::Otp->queue(),
                'units' => $units,
                'client_charge' => $charge,
                'provider_cost' => $client->defaultProvider?->cost_per_sms ?? 0,
                'sms_provider_id' => $client->default_provider_id,
                'queued_at' => now(),
                'expires_at' => $expiresAt,
                'otp_reference' => $reference,
            ]);

            $challenge = OtpChallenge::query()->create([
                'client_id' => $client->id,
                'message_id' => $message->id,
                'phone_normalized' => $normalized,
                'code_hash' => Hash::make($code),
                'purpose' => $purpose,
                'status' => 'pending',
                'request_ip' => $ip,
                'device_id' => $deviceId,
                'expires_at' => $expiresAt,
            ]);

            $this->ledger->debit($client, (float) $charge, 'OTP send '.$message->uuid, $user, $message);
            SendOtpSms::dispatch($message->id)->afterCommit();

            $this->hitRateLimits($client, $normalized, $ip, $deviceId);
            $this->auditLogger->log('otp.requested', $challenge, null, [
                'phone' => $normalized,
                'expires_at' => $expiresAt->toIso8601String(),
                'ttl_minutes' => $ttl,
            ], $user, $client->id);

            return [
                'challenge' => $challenge,
                'message' => $message,
                'expires_at' => $expiresAt,
                'reference' => $reference,
            ];
        });
    }

    public function verify(Client $client, string $to, string $code, ?string $purpose = null): bool
    {
        $normalized = $this->phoneNormalizer->normalize($to);
        if (! $normalized) {
            return false;
        }

        $challenge = OtpChallenge::query()
            ->where('client_id', $client->id)
            ->where('phone_normalized', $normalized)
            ->where('status', 'pending')
            ->when($purpose, fn ($q) => $q->where('purpose', $purpose))
            ->latest('id')
            ->first();

        if (! $challenge || $challenge->isExpired()) {
            if ($challenge) {
                $challenge->update(['status' => 'expired', 'invalidated_at' => now()]);
            }

            return false;
        }

        if (! Hash::check($code, $challenge->code_hash)) {
            return false;
        }

        $challenge->update([
            'status' => 'consumed',
            'consumed_at' => now(),
        ]);

        return true;
    }

    protected function invalidatePrevious(Client $client, string $normalized): void
    {
        $pending = OtpChallenge::query()
            ->where('client_id', $client->id)
            ->where('phone_normalized', $normalized)
            ->where('status', 'pending')
            ->get();

        foreach ($pending as $challenge) {
            $challenge->update([
                'status' => 'invalidated',
                'invalidated_at' => now(),
            ]);

            if ($challenge->message_id) {
                Message::query()
                    ->whereKey($challenge->message_id)
                    ->where('status', MessageStatus::Queued)
                    ->update([
                        'status' => MessageStatus::Cancelled,
                        'failure_reason' => 'Superseded by newer OTP request',
                    ]);
            }
        }
    }

    protected function assertRateLimits(Client $client, string $phone, ?string $ip, ?string $deviceId): void
    {
        $checks = [
            'phone:'.$client->id.':'.$phone => (int) config('sms.otp.per_phone_per_hour', 5),
            'client:'.$client->id => (int) config('sms.otp.per_client_per_hour', 200),
        ];

        if ($ip) {
            $checks['ip:'.$ip] = (int) config('sms.otp.per_ip_per_hour', 30);
        }
        if ($deviceId) {
            $checks['device:'.$deviceId] = (int) config('sms.otp.per_phone_per_hour', 5);
        }

        foreach ($checks as $key => $max) {
            if (RateLimiter::tooManyAttempts('otp:'.$key, $max)) {
                throw new RuntimeException('OTP request rate limit exceeded. Try again later.');
            }
        }
    }

    protected function hitRateLimits(Client $client, string $phone, ?string $ip, ?string $deviceId): void
    {
        foreach ([
            'phone:'.$client->id.':'.$phone,
            'client:'.$client->id,
            $ip ? 'ip:'.$ip : null,
            $deviceId ? 'device:'.$deviceId : null,
        ] as $key) {
            if ($key) {
                RateLimiter::hit('otp:'.$key, 3600);
            }
        }
    }
}
