<x-portal-layout title="Edit client" portalLabel="Administration" header="Edit {{ $client->company_name }}" :nav="\App\Support\PortalNav::admin('clients')">
    <form method="POST" action="{{ route('admin.clients.update', $client) }}" class="table-wrap max-w-3xl space-y-4 p-6">
        @csrf @method('PUT')
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="text-sm font-medium">Company name</label>
                <input name="company_name" class="input-field mt-1" value="{{ old('company_name', $client->company_name) }}" required>
            </div>
            <div>
                <label class="text-sm font-medium">Contact name</label>
                <input name="contact_name" class="input-field mt-1" value="{{ old('contact_name', $client->contact_name) }}" required>
            </div>
            <div>
                <label class="text-sm font-medium">Contact email</label>
                <input type="email" name="contact_email" class="input-field mt-1" value="{{ old('contact_email', $client->contact_email) }}" required>
            </div>
            <div>
                <label class="text-sm font-medium">Billing type</label>
                <select name="billing_type" class="input-field mt-1">
                    <option value="prepaid" @selected($client->billing_type->value==='prepaid')>Prepaid</option>
                    <option value="postpaid" @selected($client->billing_type->value==='postpaid')>Postpaid</option>
                </select>
            </div>
            <div>
                <label class="text-sm font-medium">Price per SMS</label>
                <input type="number" step="0.0001" name="price_per_sms" class="input-field mt-1" value="{{ old('price_per_sms', $client->price_per_sms) }}" required>
            </div>
            <div>
                <label class="text-sm font-medium">Credit limit</label>
                <input type="number" step="0.01" name="credit_limit" class="input-field mt-1" value="{{ old('credit_limit', $client->credit_limit) }}">
            </div>
            <div>
                <label class="text-sm font-medium">Low balance alert</label>
                <input type="number" step="0.01" name="low_balance_threshold" class="input-field mt-1" value="{{ old('low_balance_threshold', $client->low_balance_threshold) }}">
            </div>
            <div>
                <label class="text-sm font-medium">Default provider</label>
                <select name="default_provider_id" class="input-field mt-1">
                    <option value="">None</option>
                    @foreach($providers as $provider)
                        <option value="{{ $provider->id }}" @selected($client->default_provider_id==$provider->id)>{{ $provider->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="flex flex-wrap gap-4 text-sm">
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="can_send_otp" value="1" @checked($client->can_send_otp)> OTP</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="can_send_transactional" value="1" @checked($client->can_send_transactional)> Transactional</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="can_send_marketing" value="1" @checked($client->can_send_marketing)> Marketing</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="api_enabled" value="1" @checked($client->api_enabled)> API access</label>
        </div>
        <textarea name="notes" class="input-field" rows="3" placeholder="Internal notes">{{ old('notes', $client->notes) }}</textarea>
        <button class="btn-primary">Save changes</button>
    </form>
</x-portal-layout>
