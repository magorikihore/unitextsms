<x-portal-layout title="Send SMS" portalLabel="{{ $client->company_name }}" header="Send SMS" subheader="Preview recipients and cost before launch" :nav="\App\Support\PortalNav::client($client, 'send')">
    <form method="POST" action="{{ route('client.send.preview', $client) }}" enctype="multipart/form-data" class="table-wrap max-w-3xl space-y-4 p-6">
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="text-sm font-medium">Campaign / message name</label>
                <input name="name" class="input-field mt-1" value="{{ old('name') }}" required>
            </div>
            <div>
                <label class="text-sm font-medium">Approved sender ID</label>
                <select name="sender_id_id" class="input-field mt-1" required>
                    <option value="">Select sender</option>
                    @foreach($senderIds as $sid)
                        <option value="{{ $sid->id }}">{{ $sid->sender }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-medium">Category</label>
                <select name="category" class="input-field mt-1" required>
                    <option value="transactional">Transactional</option>
                    <option value="otp">OTP</option>
                    <option value="alert">Alert</option>
                    <option value="marketing">Marketing</option>
                </select>
            </div>
            <div>
                <label class="text-sm font-medium">Template (optional)</label>
                <select name="template_id" class="input-field mt-1" onchange="if(this.selectedOptions[0].dataset.body){document.getElementById('message_body').value=this.selectedOptions[0].dataset.body}">
                    <option value="">None</option>
                    @foreach($templates as $template)
                        <option value="{{ $template->id }}" data-body="{{ $template->body }}">{{ $template->name }} ({{ $template->language }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-medium">Schedule (optional)</label>
                <input type="datetime-local" name="scheduled_at" class="input-field mt-1" value="{{ old('scheduled_at') }}">
            </div>
        </div>
        <div>
            <label class="text-sm font-medium">Message</label>
            <textarea id="message_body" name="message_body" rows="5" class="input-field mt-1" required placeholder="Hello @{{first_name}}, your code is ...">{{ old('message_body') }}</textarea>
            <p class="mt-1 text-xs text-slate-500">Variables: @{{first_name}}, @{{last_name}}, custom fields</p>
        </div>
        <div>
            <label class="text-sm font-medium">Recipients (one per line, or paste / upload)</label>
            <textarea name="numbers" rows="6" class="input-field mt-1" placeholder="0758460813&#10;255754000111">{{ old('numbers') }}</textarea>
        </div>
        <div>
            <label class="text-sm font-medium">Upload CSV / Excel</label>
            <input type="file" name="file" accept=".csv,.xlsx,.xls,.txt" class="mt-1 block text-sm">
        </div>
        <button class="btn-primary">Preview before sending</button>
    </form>
</x-portal-layout>
