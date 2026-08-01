<x-portal-layout title="New client" portalLabel="Administration" header="Create client" :nav="\App\Support\PortalNav::admin('clients')">
    <form method="POST" action="{{ route('admin.clients.store') }}" class="table-wrap max-w-3xl space-y-4 p-6">
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="text-sm font-medium">Company name</label>
                <input name="company_name" class="input-field mt-1" value="{{ old('company_name') }}" required>
            </div>
            <div>
                <label class="text-sm font-medium">Trading name</label>
                <input name="trading_name" class="input-field mt-1" value="{{ old('trading_name') }}">
            </div>
            <div>
                <label class="text-sm font-medium">Contact name</label>
                <input name="contact_name" class="input-field mt-1" value="{{ old('contact_name') }}" required>
            </div>
            <div>
                <label class="text-sm font-medium">Contact email</label>
                <input type="email" name="contact_email" class="input-field mt-1" value="{{ old('contact_email') }}" required>
            </div>
            <div>
                <label class="text-sm font-medium">Contact phone</label>
                <input name="contact_phone" class="input-field mt-1" value="{{ old('contact_phone') }}">
            </div>
            <div>
                <label class="text-sm font-medium">Billing type</label>
                <select name="billing_type" class="input-field mt-1">
                    <option value="prepaid">Prepaid</option>
                    <option value="postpaid">Postpaid</option>
                </select>
            </div>
            <div>
                <label class="text-sm font-medium">Price per SMS</label>
                <input type="number" step="0.0001" name="price_per_sms" class="input-field mt-1" value="{{ old('price_per_sms', '0.0500') }}" required>
            </div>
            <div>
                <label class="text-sm font-medium">Credit limit</label>
                <input type="number" step="0.01" name="credit_limit" class="input-field mt-1" value="{{ old('credit_limit', '0') }}">
            </div>
            <div>
                <label class="text-sm font-medium">Default provider</label>
                <select name="default_provider_id" class="input-field mt-1">
                    <option value="">None</option>
                    @foreach($providers as $provider)
                        <option value="{{ $provider->id }}">{{ $provider->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-medium">Owner email (optional)</label>
                <input type="email" name="owner_email" class="input-field mt-1" value="{{ old('owner_email') }}">
            </div>
        </div>
        <div class="flex flex-wrap gap-4 text-sm">
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="can_send_otp" value="1" checked> OTP</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="can_send_transactional" value="1" checked> Transactional</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="can_send_marketing" value="1"> Marketing</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="api_enabled" value="1"> API access</label>
        </div>
        <button class="btn-primary">Create client</button>
    </form>
</x-portal-layout>
