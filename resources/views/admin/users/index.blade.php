@php
    $userRows = $users->getCollection()->map(fn ($u) => [
        'id' => $u->id,
        'name' => $u->name,
        'email' => $u->email,
        'platform_role' => $u->platform_role?->value,
        'department' => $u->department ?? '',
        'is_active' => (bool) $u->is_active,
        'update_url' => route('admin.users.update', $u),
        'reset_url' => route('admin.users.reset-password', $u),
    ])->values();
@endphp

<x-portal-layout title="Users & Roles" portalLabel="Administration" header="Users & Roles" subheader="Platform staff — roles, departments, access" :nav="\App\Support\PortalNav::admin('users')">
    <div
        x-data="{
            openAdd: false,
            openEdit: false,
            openReset: false,
            users: @js($userRows),
            edit: { id: null, name: '', email: '', platform_role: 'support', department: '', is_active: true, update_url: '', reset_url: '' },
            reset: { name: '', email: '', reset_url: '' },
            startEdit(user) {
                this.edit = { ...user, department: user.department || '', is_active: !!user.is_active };
                this.openEdit = true;
                this.openAdd = false;
                this.openReset = false;
            },
            startReset(user) {
                this.reset = { name: user.name, email: user.email, reset_url: user.reset_url };
                this.openReset = true;
                this.openAdd = false;
                this.openEdit = false;
            }
        }"
    >
        <section class="table-wrap overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-3 py-2">
                <form method="GET" class="toolbar-row min-w-0 flex-1">
                    <input name="q" class="input-field-sm w-48" placeholder="Search name, email, dept" value="{{ $filters['q'] ?? '' }}">
                    <select name="role" class="input-field-sm w-auto min-w-[9rem]">
                        <option value="">All roles</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->value }}" @selected(($filters['role'] ?? null)===$role->value)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                    <select name="department" class="input-field-sm w-auto min-w-[9rem]">
                        <option value="">All departments</option>
                        @foreach($departments as $department)
                            <option value="{{ $department }}" @selected(($filters['department'] ?? null)===$department)>{{ $department }}</option>
                        @endforeach
                    </select>
                    <button class="btn-sm-secondary">Filter</button>
                </form>
                <button type="button" class="btn-sm-primary" @click="openAdd = true; openEdit = false; openReset = false">+ Add user</button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Name</th>
                        <th class="px-3 py-2">Email</th>
                        <th class="px-3 py-2">Role</th>
                        <th class="px-3 py-2">Department</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2 text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    @forelse($users as $index => $user)
                        <tr class="cursor-pointer hover:bg-slate-50/80" @click="startEdit(users[{{ $index }}])">
                            <td class="px-3 py-2 font-medium text-slate-900">{{ $user->name }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $user->email }}</td>
                            <td class="px-3 py-2"><span class="badge bg-blue-50 text-blue-800">{{ $user->platform_role?->label() }}</span></td>
                            <td class="px-3 py-2 text-slate-600">{{ $user->department ?: '—' }}</td>
                            <td class="px-3 py-2">
                                <span class="badge {{ $user->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $user->is_active ? 'active' : 'inactive' }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right" @click.stop>
                                <div class="inline-flex gap-1">
                                    <button type="button" class="btn-sm-secondary" @click="startEdit(users[{{ $index }}])">Edit</button>
                                    <button type="button" class="btn-sm-secondary" @click="startReset(users[{{ $index }}])">Reset password</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-8 text-center text-slate-500">No platform users found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        <div class="mt-3">{{ $users->links() }}</div>

        {{-- Add user modal --}}
        <div x-show="openAdd" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" @keydown.escape.window="openAdd = false">
            <div class="w-full max-w-lg rounded-xl bg-white p-5 shadow-xl" @click.outside="openAdd = false">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Add user</h2>
                    <button type="button" class="text-slate-400 hover:text-slate-700" @click="openAdd = false">✕</button>
                </div>
                <form method="POST" action="{{ route('admin.users.store') }}" data-ajax class="grid gap-2 sm:grid-cols-2" novalidate>
                    @csrf
                    <div data-form-error class="hidden sm:col-span-2 rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-sm text-red-700"></div>
                    <input name="name" class="input-field-sm" placeholder="Full name" required>
                    <input type="email" name="email" class="input-field-sm" placeholder="Email" required>
                    <x-password-input name="password" size="sm" placeholder="Password" required />
                    <select name="platform_role" class="input-field-sm" required>
                        @foreach($roles as $role)
                            <option value="{{ $role->value }}">{{ $role->label() }}</option>
                        @endforeach
                    </select>
                    <input name="department" class="input-field-sm" list="department-options" placeholder="Department">
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300">
                        Active
                    </label>
                    <div class="sm:col-span-2 flex justify-end gap-2 pt-1">
                        <button type="button" class="btn-sm-secondary" @click="openAdd = false">Cancel</button>
                        <button type="submit" data-loading-text="Saving…" class="btn-sm-primary">Create user</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Edit user modal --}}
        <div x-show="openEdit" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" @keydown.escape.window="openEdit = false">
            <div class="w-full max-w-lg rounded-xl bg-white p-5 shadow-xl" @click.outside="openEdit = false">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Update user</h2>
                    <button type="button" class="text-slate-400 hover:text-slate-700" @click="openEdit = false">✕</button>
                </div>
                <form method="POST" :action="edit.update_url" data-ajax class="grid gap-2 sm:grid-cols-2" novalidate>
                    @csrf
                    @method('PUT')
                    <div data-form-error class="hidden sm:col-span-2 rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-sm text-red-700"></div>
                    <input name="name" class="input-field-sm" x-model="edit.name" placeholder="Full name" required>
                    <input type="email" name="email" class="input-field-sm" x-model="edit.email" placeholder="Email" required>
                    <select name="platform_role" class="input-field-sm" x-model="edit.platform_role" required>
                        @foreach($roles as $role)
                            <option value="{{ $role->value }}">{{ $role->label() }}</option>
                        @endforeach
                    </select>
                    <input name="department" class="input-field-sm" list="department-options" x-model="edit.department" placeholder="Department">
                    <label class="flex items-center gap-2 text-sm text-slate-600 sm:col-span-2">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" x-model="edit.is_active" class="rounded border-slate-300">
                        Active
                    </label>
                    <div class="sm:col-span-2 flex flex-wrap justify-between gap-2 pt-1">
                        <button type="button" class="btn-sm-secondary" @click="startReset(edit)">Reset password</button>
                        <div class="flex gap-2">
                            <button type="button" class="btn-sm-secondary" @click="openEdit = false">Cancel</button>
                            <button type="submit" data-loading-text="Saving…" class="btn-sm-primary">Save changes</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Reset password modal --}}
        <div x-show="openReset" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" @keydown.escape.window="openReset = false">
            <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl" @click.outside="openReset = false">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Reset password</h2>
                    <button type="button" class="text-slate-400 hover:text-slate-700" @click="openReset = false">✕</button>
                </div>
                <p class="mb-3 text-sm text-slate-600">
                    Set a new password for <span class="font-medium" x-text="reset.name"></span>
                    <span class="text-slate-400" x-text="'(' + reset.email + ')'"></span>
                </p>
                <form method="POST" :action="reset.reset_url" data-ajax class="grid gap-2" novalidate>
                    @csrf
                    <div data-form-error class="hidden rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-sm text-red-700"></div>
                    <x-password-input name="password" size="sm" placeholder="New password" required autocomplete="new-password" />
                    <x-password-input name="password_confirmation" size="sm" placeholder="Confirm password" required autocomplete="new-password" />
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" class="btn-sm-secondary" @click="openReset = false">Cancel</button>
                        <button type="submit" data-loading-text="Saving…" class="btn-sm-primary">Reset password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <datalist id="department-options">
        @foreach($suggestedDepartments as $department)
            <option value="{{ $department }}"></option>
        @endforeach
        @foreach($departments as $department)
            <option value="{{ $department }}"></option>
        @endforeach
    </datalist>
</x-portal-layout>
