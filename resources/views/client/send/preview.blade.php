<x-portal-layout title="Confirm send" portalLabel="{{ $client->company_name }}" header="Confirm before launch" subheader="Review validation and cost, then confirm" :nav="\App\Support\PortalNav::client($client, 'send')">
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Valid numbers" :value="count($preview['valid'])" tone="ok" />
        <x-stat label="Invalid" :value="count($preview['invalid'])" tone="bad" />
        <x-stat label="Duplicates removed" :value="$preview['duplicates_removed']" />
        <x-stat label="Opted out" :value="count($preview['opted_out'])" tone="warn" />
        <x-stat label="Estimated units" :value="$preview['estimated_units']" />
        <x-stat label="Estimated cost" :value="number_format($preview['estimated_cost'], 2)" />
        <x-stat label="Remaining balance" :value="number_format($preview['remaining_balance'], 2)" :tone="$preview['remaining_balance'] < 0 ? 'bad' : 'ok'" />
        <x-stat label="Est. completion" :value="$preview['estimated_completion_seconds'].'s'" />
    </div>

    <div class="table-wrap mb-6 p-5 text-sm">
        <div class="font-semibold">Message</div>
        <p class="mt-2 whitespace-pre-wrap text-slate-700">{{ $data['message_body'] }}</p>
    </div>

    <form method="POST" action="{{ route('client.send.store', $client) }}" class="table-wrap space-y-4 p-5">
        @csrf
        <input type="hidden" name="name" value="{{ $data['name'] }}">
        <input type="hidden" name="sender_id_id" value="{{ $data['sender_id_id'] }}">
        <input type="hidden" name="template_id" value="{{ $data['template_id'] ?? '' }}">
        <input type="hidden" name="category" value="{{ $data['category'] }}">
        <input type="hidden" name="message_body" value="{{ $data['message_body'] }}">
        <input type="hidden" name="scheduled_at" value="{{ $data['scheduled_at'] ?? '' }}">
        <input type="hidden" name="numbers" value="{{ $numbers_raw }}">
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="confirm" value="1" required>
            I confirm this will charge credits and launch the send
        </label>
        <div class="flex flex-wrap gap-2">
            <button name="action" value="launch" class="btn-primary" @disabled($preview['remaining_balance'] < 0 || count($preview['valid'])===0)>Launch now</button>
            <button name="action" value="schedule" class="btn-secondary" @disabled(empty($data['scheduled_at']))>Schedule</button>
            <button name="action" value="draft" class="btn-secondary">Save draft</button>
            @if(count($preview['valid']) === 1)
                <button name="action" value="single" class="btn-secondary">Send as single SMS</button>
            @endif
        </div>
    </form>
</x-portal-layout>
