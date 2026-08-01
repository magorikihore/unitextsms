<?php

namespace App\Http\Controllers\Client;

use App\Enums\ClientRole;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Client $client): View
    {
        return view('client.team.index', [
            'client' => $client->load('users'),
            'roles' => ClientRole::cases(),
        ]);
    }

    public function store(Request $request, Client $client): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'role' => ['required', Rule::enum(ClientRole::class)],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $user = User::query()->firstOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['name'],
                'password' => Hash::make($data['password'] ?? Str::password(12)),
                'email_verified_at' => now(),
            ]
        );

        $client->users()->syncWithoutDetaching([
            $user->id => ['role' => $data['role'], 'is_active' => true],
        ]);

        $this->auditLogger->log('team.member.added', $user, null, ['role' => $data['role']], $request->user(), $client->id);

        return back()->with('success', 'Team member added.');
    }
}
