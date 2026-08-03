<?php

namespace App\Http\Controllers\Client;

use App\Enums\ClientRole;
use App\Enums\SenderIdStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\SenderId;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request, Client $client): View
    {
        $role = $this->clientRole($request);

        return view('client.settings.index', [
            'client' => $client,
            'user' => $request->user(),
            'canManageOrg' => $role?->canManageSettings() ?? false,
            'senderIds' => SenderId::query()
                ->where('client_id', $client->id)
                ->where('status', SenderIdStatus::Approved)
                ->orderBy('sender')
                ->get(),
        ]);
    }

    public function updateProfile(Request $request, Client $client): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id),
            ],
        ]);

        $before = $user->only(['name', 'email']);
        $user->fill($data);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->auditLogger->log('user.profile.updated', $user, $before, $user->only(['name', 'email']), $user, $client->id);

        return $this->ajaxRedirect($request, route('client.settings.index', $client), 'Profile updated.');
    }

    public function updatePassword(Request $request, Client $client): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($data['password']),
        ]);

        $this->auditLogger->log('user.password.updated', $request->user(), null, null, $request->user(), $client->id);

        return $this->ajaxRedirect($request, route('client.settings.index', $client), 'Password updated.');
    }

    public function updateDefaults(Request $request, Client $client): RedirectResponse|JsonResponse
    {
        $this->assertCanManage($request);

        $data = $request->validate([
            'default_sender_id_id' => [
                'nullable',
                Rule::exists('sender_ids', 'id')
                    ->where(fn ($q) => $q->where('client_id', $client->id)->where('status', SenderIdStatus::Approved->value)),
            ],
            'low_balance_threshold' => ['required', 'numeric', 'min:0'],
        ]);

        $before = $client->only(['default_sender_id_id', 'low_balance_threshold']);
        $client->update([
            'default_sender_id_id' => $data['default_sender_id_id'] ?: null,
            'low_balance_threshold' => $data['low_balance_threshold'],
        ]);

        $this->auditLogger->log(
            'client.defaults.updated',
            $client,
            $before,
            $client->only(['default_sender_id_id', 'low_balance_threshold']),
            $request->user(),
            $client->id,
        );

        return $this->ajaxRedirect($request, route('client.settings.index', $client), 'Sending defaults saved.');
    }

    public function updateCompany(Request $request, Client $client): RedirectResponse|JsonResponse
    {
        $this->assertCanManage($request);

        $data = $request->validate([
            'trading_name' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
        ]);

        $before = $client->only(array_keys($data));
        $client->update($data);

        $this->auditLogger->log('client.company.updated', $client, $before, $data, $request->user(), $client->id);

        return $this->ajaxRedirect($request, route('client.settings.index', $client), 'Organisation details saved.');
    }

    private function clientRole(Request $request): ?ClientRole
    {
        $role = $request->attributes->get('client_role');

        if ($role instanceof ClientRole) {
            return $role;
        }

        if (is_string($role) && $role !== '') {
            return ClientRole::tryFrom($role);
        }

        if ($request->user()?->isPlatformUser()) {
            return ClientRole::Owner;
        }

        return null;
    }

    private function assertCanManage(Request $request): void
    {
        $role = $this->clientRole($request);

        if (! $role?->canManageSettings()) {
            abort(403, 'Only owners and managers can change organisation settings.');
        }
    }
}
