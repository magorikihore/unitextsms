<x-portal-layout title="Account" portalLabel="Administration" header="Account" subheader="Update your profile and password" icon="settings" :nav="\App\Support\PortalNav::admin('account')">
    <div class="mx-auto max-w-3xl space-y-5">
        <form method="POST" action="{{ route('admin.account.profile') }}" data-ajax class="table-wrap space-y-4 p-5" novalidate>
            @csrf
            @method('PUT')
            <div class="flex items-center gap-2">
                <span class="text-slate-300"><x-icon name="users" class="h-5 w-5" /></span>
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Your profile</h2>
                    <p class="text-sm text-slate-500">Name, email and department for your admin account.</p>
                </div>
            </div>
            <div data-form-error class="hidden rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-sm text-red-700"></div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="text-sm font-medium text-slate-700">Full name</label>
                    <input name="name" class="input-field-sm mt-1" value="{{ old('name', $user->name) }}" required autocomplete="name">
                    <p class="field-error hidden" data-error-for="name"></p>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Email</label>
                    <input type="email" name="email" class="input-field-sm mt-1" value="{{ old('email', $user->email) }}" required autocomplete="username">
                    <p class="field-error hidden" data-error-for="email"></p>
                </div>
                <div class="sm:col-span-2">
                    <label class="text-sm font-medium text-slate-700">Department <span class="font-normal text-slate-400">(optional)</span></label>
                    <input name="department" class="input-field-sm mt-1" value="{{ old('department', $user->department) }}">
                    <p class="field-error hidden" data-error-for="department"></p>
                </div>
            </div>
            <button type="submit" data-loading-text="Saving…" class="btn-primary">Save profile</button>
        </form>

        <form method="POST" action="{{ route('admin.account.password') }}" data-ajax class="table-wrap space-y-4 p-5" novalidate>
            @csrf
            @method('PUT')
            <div class="flex items-center gap-2">
                <span class="text-slate-300"><x-icon name="settings" class="h-5 w-5" /></span>
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Change password</h2>
                    <p class="text-sm text-slate-500">Use a strong password you do not reuse elsewhere.</p>
                </div>
            </div>
            <div data-form-error class="hidden rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-sm text-red-700"></div>
            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="text-sm font-medium text-slate-700">Current password</label>
                    <x-password-input name="current_password" size="sm" class="mt-1" required autocomplete="current-password" />
                    <p class="field-error hidden" data-error-for="current_password"></p>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">New password</label>
                    <x-password-input name="password" size="sm" class="mt-1" required autocomplete="new-password" />
                    <p class="field-error hidden" data-error-for="password"></p>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Confirm password</label>
                    <x-password-input name="password_confirmation" size="sm" class="mt-1" required autocomplete="new-password" />
                    <p class="field-error hidden" data-error-for="password_confirmation"></p>
                </div>
            </div>
            <button type="submit" data-loading-text="Updating…" class="btn-primary">Update password</button>
        </form>
    </div>
</x-portal-layout>
