<x-portal-layout title="Settings" portalLabel="{{ $client->company_name }}" header="Settings" subheader="Profile, password, sender defaults and organisation details" icon="settings" :nav="\App\Support\PortalNav::client($client, 'settings')">
    <div class="mx-auto max-w-3xl space-y-5">
        <form method="POST" action="{{ route('client.settings.profile', $client) }}" data-ajax class="table-wrap space-y-4 p-5" novalidate>
            @csrf
            @method('PUT')
            <div class="flex items-center gap-2">
                <span class="text-slate-300"><x-icon name="users" class="h-5 w-5" /></span>
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Your profile</h2>
                    <p class="text-sm text-slate-500">Name and email used to sign in.</p>
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
            </div>
            <button type="submit" data-loading-text="Saving…" class="btn-primary">Save profile</button>
        </form>

        <form method="POST" action="{{ route('client.settings.password', $client) }}" data-ajax class="table-wrap space-y-4 p-5" novalidate>
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

        @if($canManageOrg)
            <form method="POST" action="{{ route('client.settings.defaults', $client) }}" data-ajax class="table-wrap space-y-4 p-5" novalidate>
                @csrf
                @method('PUT')
                <div class="flex items-center gap-2">
                    <span class="text-slate-300"><x-icon name="sender" class="h-5 w-5" /></span>
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Sending defaults</h2>
                        <p class="text-sm text-slate-500">Pre-select an approved sender ID when composing SMS.</p>
                    </div>
                </div>
                <div data-form-error class="hidden rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-sm text-red-700"></div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-slate-700">Default sender ID</label>
                        <select name="default_sender_id_id" class="input-field-sm mt-1">
                            <option value="">No default</option>
                            @foreach($senderIds as $sid)
                                <option value="{{ $sid->id }}" @selected((string) old('default_sender_id_id', $client->default_sender_id_id) === (string) $sid->id)>
                                    {{ $sid->sender }}
                                </option>
                            @endforeach
                        </select>
                        <p class="field-error hidden" data-error-for="default_sender_id_id"></p>
                        @if($senderIds->isEmpty())
                            <p class="mt-1 text-xs text-slate-500">
                                No approved sender IDs yet.
                                <a href="{{ route('client.sender-ids.index', $client) }}" data-ajax-nav class="text-teal-700 underline">Request one</a>
                            </p>
                        @endif
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Low balance alert (credits)</label>
                        <input type="number" step="0.01" min="0" name="low_balance_threshold" class="input-field-sm mt-1"
                               value="{{ old('low_balance_threshold', $client->low_balance_threshold) }}" required>
                        <p class="field-error hidden" data-error-for="low_balance_threshold"></p>
                    </div>
                </div>
                <button type="submit" data-loading-text="Saving…" class="btn-primary">Save defaults</button>
            </form>

            <form method="POST" action="{{ route('client.settings.company', $client) }}" data-ajax class="table-wrap space-y-4 p-5" novalidate>
                @csrf
                @method('PUT')
                <div class="flex items-center gap-2">
                    <span class="text-slate-300"><x-icon name="clients" class="h-5 w-5" /></span>
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Organisation details</h2>
                        <p class="text-sm text-slate-500">Contact information shown on invoices and support.</p>
                    </div>
                </div>
                <div data-form-error class="hidden rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-sm text-red-700"></div>
                <div class="rounded-md border border-slate-100 bg-slate-50/80 px-3 py-2 text-sm text-slate-600">
                    Company name: <span class="font-medium text-slate-900">{{ $client->company_name }}</span>
                    <span class="text-slate-400">· contact admin to rename</span>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-slate-700">Trading name</label>
                        <input name="trading_name" class="input-field-sm mt-1" value="{{ old('trading_name', $client->trading_name) }}">
                        <p class="field-error hidden" data-error-for="trading_name"></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Primary contact</label>
                        <input name="contact_name" class="input-field-sm mt-1" value="{{ old('contact_name', $client->contact_name) }}" required>
                        <p class="field-error hidden" data-error-for="contact_name"></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Contact email</label>
                        <input type="email" name="contact_email" class="input-field-sm mt-1" value="{{ old('contact_email', $client->contact_email) }}" required>
                        <p class="field-error hidden" data-error-for="contact_email"></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Contact phone</label>
                        <input name="contact_phone" class="input-field-sm mt-1" value="{{ old('contact_phone', $client->contact_phone) }}">
                        <p class="field-error hidden" data-error-for="contact_phone"></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Address</label>
                        <input name="address" class="input-field-sm mt-1" value="{{ old('address', $client->address) }}">
                        <p class="field-error hidden" data-error-for="address"></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">City</label>
                        <input name="city" class="input-field-sm mt-1" value="{{ old('city', $client->city) }}">
                        <p class="field-error hidden" data-error-for="city"></p>
                    </div>
                </div>
                <button type="submit" data-loading-text="Saving…" class="btn-primary">Save organisation</button>
            </form>
        @endif
    </div>
</x-portal-layout>
