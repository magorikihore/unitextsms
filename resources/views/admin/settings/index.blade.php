<x-portal-layout title="System Settings" portalLabel="Administration" header="System Settings" subheader="SMS capacity, DLR security, Mailgun and PayIn" icon="settings" :nav="\App\Support\PortalNav::admin('settings')">
    <form method="POST" action="{{ route('admin.settings.update') }}" data-ajax class="mx-auto max-w-3xl space-y-5" novalidate>
        @csrf
        @method('PUT')
        <div data-form-error class="hidden rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></div>

        <section class="table-wrap space-y-3 p-5">
            <h2 class="text-lg font-semibold text-slate-900">Throughput (TPS)</h2>
            <p class="text-sm text-slate-500">OTP + transactional + bulk must not exceed provider TPS.</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="text-sm">Provider ceiling
                    <input type="number" name="provider_tps" class="input-field-sm mt-1" value="{{ $settings['provider_tps'] }}" min="1" required>
                </label>
                <label class="text-sm">OTP TPS
                    <input type="number" name="otp_tps" class="input-field-sm mt-1" value="{{ $settings['otp_tps'] }}" min="1" required>
                </label>
                <label class="text-sm">Transactional TPS
                    <input type="number" name="transactional_tps" class="input-field-sm mt-1" value="{{ $settings['transactional_tps'] }}" min="1" required>
                </label>
                <label class="text-sm">Bulk TPS
                    <input type="number" name="bulk_tps" class="input-field-sm mt-1" value="{{ $settings['bulk_tps'] }}" min="0" required>
                </label>
            </div>
        </section>

        <section class="table-wrap space-y-3 p-5">
            <h2 class="text-lg font-semibold text-slate-900">OTP protection</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="text-sm">OTP TTL (minutes)
                    <input type="number" name="otp_ttl_minutes" class="input-field-sm mt-1" value="{{ $settings['otp_ttl_minutes'] }}" min="1" max="30" required>
                </label>
                <label class="text-sm">Pause bulk if OTP latency exceeds (ms)
                    <input type="number" name="otp_latency_pause_ms" class="input-field-sm mt-1" value="{{ $settings['otp_latency_pause_ms'] }}" min="100" required>
                </label>
                <label class="text-sm">Pause bulk if OTP failure rate exceeds (0–1)
                    <input type="number" step="0.01" name="otp_failure_pause_rate" class="input-field-sm mt-1" value="{{ $settings['otp_failure_pause_rate'] }}" min="0.01" max="1" required>
                </label>
            </div>
        </section>

        <section class="table-wrap space-y-3 p-5">
            <h2 class="text-lg font-semibold text-slate-900">Marketing window</h2>
            <div class="grid gap-3 sm:grid-cols-3">
                <label class="text-sm">Start
                    <input type="time" name="marketing_hours_start" class="input-field-sm mt-1" value="{{ $settings['marketing_hours_start'] }}" required>
                </label>
                <label class="text-sm">End
                    <input type="time" name="marketing_hours_end" class="input-field-sm mt-1" value="{{ $settings['marketing_hours_end'] }}" required>
                </label>
                <label class="text-sm">Timezone
                    <input name="marketing_timezone" class="input-field-sm mt-1" value="{{ $settings['marketing_timezone'] }}" required>
                </label>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="hidden" name="bulk_paused" value="0">
                <input type="checkbox" name="bulk_paused" value="1" @checked($settings['bulk_paused']) class="rounded border-slate-300">
                Manually pause bulk / marketing sending
            </label>
        </section>

        <section class="table-wrap space-y-3 p-5">
            <h2 class="text-lg font-semibold text-slate-900">DLR IP allow list</h2>
            <p class="text-sm text-slate-500">Only these IPs/CIDRs may POST to <code class="text-xs">/dlr/jasmin</code>. Required in production (fail closed if empty).</p>
            <label class="block text-sm">Allowed IPs
                <textarea name="dlr_allowed_ips" rows="3" class="input-field-sm mt-1 font-mono text-xs" placeholder="41.220.143.37&#10;203.0.113.0/24">{{ $settings['dlr_allowed_ips'] }}</textarea>
            </label>
            <p class="field-error hidden" data-error-for="dlr_allowed_ips"></p>
        </section>

        <section class="table-wrap space-y-3 p-5">
            <h2 class="text-lg font-semibold text-slate-900">Mailgun / email</h2>
            <p class="text-sm text-slate-500">Used for password reset and other notifications. Leave secret blank to keep the saved value.</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="text-sm">Mailer
                    <select name="mail_mailer" class="input-field-sm mt-1">
                        <option value="log" @selected($settings['mail_mailer'] === 'log')>Log (dev)</option>
                        <option value="mailgun" @selected($settings['mail_mailer'] === 'mailgun')>Mailgun</option>
                        <option value="smtp" @selected($settings['mail_mailer'] === 'smtp')>SMTP</option>
                    </select>
                </label>
                <label class="text-sm">Mailgun domain
                    <input name="mailgun_domain" class="input-field-sm mt-1" value="{{ $settings['mailgun_domain'] }}" placeholder="mg.triptz.net">
                </label>
                <label class="text-sm">Mailgun private API key
                    <input type="password" name="mailgun_secret" class="input-field-sm mt-1" autocomplete="new-password" placeholder="{{ $settings['mailgun_secret_set'] ? '•••• saved — leave blank to keep' : 'key-…' }}">
                </label>
                <label class="text-sm">Mailgun endpoint
                    <input name="mailgun_endpoint" class="input-field-sm mt-1" value="{{ $settings['mailgun_endpoint'] }}" placeholder="api.mailgun.net or api.eu.mailgun.net">
                </label>
                <label class="text-sm">From address
                    <input type="email" name="mail_from_address" class="input-field-sm mt-1" value="{{ $settings['mail_from_address'] }}" placeholder="noreply@triptz.net">
                </label>
                <label class="text-sm">From name
                    <input name="mail_from_name" class="input-field-sm mt-1" value="{{ $settings['mail_from_name'] }}" placeholder="UniNText">
                </label>
            </div>
        </section>

        <section class="table-wrap space-y-3 p-5">
            <h2 class="text-lg font-semibold text-slate-900">PayIn deposits</h2>
            <p class="text-sm text-slate-500">Mobile money top-ups. Webhook: <code class="text-xs">{{ url('/webhooks/payin') }}</code>. Secrets blank = keep saved.</p>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="hidden" name="payin_enabled" value="0">
                <input type="checkbox" name="payin_enabled" value="1" @checked($settings['payin_enabled']) class="rounded border-slate-300">
                Enable PayIn deposits (shows Deposit button for clients)
            </label>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="text-sm sm:col-span-2">API base URL
                    <input name="payin_base_url" class="input-field-sm mt-1" value="{{ $settings['payin_base_url'] }}" placeholder="https://api.payin.co.tz/api/v1">
                </label>
                <label class="text-sm">API key
                    <input type="password" name="payin_api_key" class="input-field-sm mt-1" autocomplete="new-password" placeholder="{{ $settings['payin_api_key_set'] ? '•••• saved — leave blank to keep' : 'pk_…' }}">
                </label>
                <label class="text-sm">API secret
                    <input type="password" name="payin_api_secret" class="input-field-sm mt-1" autocomplete="new-password" placeholder="{{ $settings['payin_api_secret_set'] ? '•••• saved — leave blank to keep' : 'sk_…' }}">
                </label>
                <label class="text-sm">Webhook secret
                    <input type="password" name="payin_webhook_secret" class="input-field-sm mt-1" autocomplete="new-password" placeholder="{{ $settings['payin_webhook_secret_set'] ? '•••• saved — leave blank to keep' : 'whsec_…' }}">
                </label>
                <label class="text-sm">Currency
                    <input name="payin_currency" class="input-field-sm mt-1" value="{{ $settings['payin_currency'] }}" placeholder="TZS">
                </label>
                <label class="text-sm">Minimum amount
                    <input type="number" name="payin_min_amount" class="input-field-sm mt-1" value="{{ $settings['payin_min_amount'] }}" min="1">
                </label>
            </div>
        </section>

        <button type="submit" data-loading-text="Saving…" class="btn-primary">Save settings</button>
    </form>
</x-portal-layout>
