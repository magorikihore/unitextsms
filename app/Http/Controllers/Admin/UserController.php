<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request): View
    {
        $users = User::query()
            ->whereNotNull('platform_role')
            ->when($request->role, fn ($q, $role) => $q->where('platform_role', $role))
            ->when($request->department, fn ($q, $department) => $q->where('department', $department))
            ->when($request->filled('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->when($request->q, function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('department', 'like', "%{$term}%");
                });
            })
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        $departments = User::query()
            ->whereNotNull('platform_role')
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->orderBy('department')
            ->pluck('department');

        return view('admin.users.index', [
            'users' => $users,
            'roles' => PlatformRole::cases(),
            'departments' => $departments,
            'suggestedDepartments' => ['Management', 'Operations', 'Finance', 'Support', 'Engineering', 'Compliance'],
            'filters' => $request->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'platform_role' => ['required', Rule::enum(PlatformRole::class)],
            'department' => ['nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'platform_role' => $data['platform_role'],
            'department' => $data['department'] ?: null,
            'is_active' => $request->boolean('is_active', true),
            'email_verified_at' => now(),
        ]);

        $this->auditLogger->log('platform_user.created', $user, null, [
            'email' => $user->email,
            'platform_role' => $user->platform_role?->value,
            'department' => $user->department,
        ], $request->user());

        return $this->ajaxRedirect($request, route('admin.users.index'), 'User created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isPlatformUser(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'platform_role' => ['required', Rule::enum(PlatformRole::class)],
            'department' => ['nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $active = $request->boolean('is_active');
        $newRole = $data['platform_role'] instanceof PlatformRole
            ? $data['platform_role']
            : PlatformRole::from($data['platform_role']);

        if ($user->id === $request->user()->id && ! $active) {
            return $this->ajaxBack($request, 'You cannot deactivate your own account.', [
                'is_active' => 'You cannot deactivate your own account.',
            ]);
        }

        if (
            $user->platform_role === PlatformRole::Owner
            && $newRole !== PlatformRole::Owner
            && User::query()->where('platform_role', PlatformRole::Owner)->where('is_active', true)->count() <= 1
        ) {
            return $this->ajaxBack($request, 'At least one active platform owner is required.', [
                'platform_role' => 'At least one active platform owner is required.',
            ]);
        }

        $old = $user->only(['name', 'email', 'platform_role', 'department', 'is_active']);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'platform_role' => $data['platform_role'],
            'department' => $data['department'] ?: null,
            'is_active' => $active,
        ]);

        $this->auditLogger->log(
            'platform_user.updated',
            $user,
            $old,
            $user->only(['name', 'email', 'platform_role', 'department', 'is_active']),
            $request->user(),
        );

        return $this->ajaxRedirect($request, route('admin.users.index'), 'User updated.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isPlatformUser(), 404);

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->update(['password' => $data['password']]);

        $this->auditLogger->log('platform_user.password_reset', $user, null, [
            'email' => $user->email,
        ], $request->user());

        return $this->ajaxRedirect($request, route('admin.users.index'), 'Password reset for '.$user->email.'.');
    }
}
