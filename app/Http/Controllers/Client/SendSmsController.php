<?php

namespace App\Http\Controllers\Client;

use App\Enums\MessageCategory;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\SenderId;
use App\Models\Template;
use App\Services\RecipientPreviewService;
use App\Services\SmsDispatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class SendSmsController extends Controller
{
    public function __construct(
        private readonly RecipientPreviewService $previewService,
        private readonly SmsDispatchService $dispatch,
    ) {}

    public function create(Client $client): View
    {
        return view('client.send.create', [
            'client' => $client,
            'senderIds' => SenderId::query()->where('client_id', $client->id)->where('status', 'approved')->get(),
            'templates' => Template::query()->where('client_id', $client->id)->where('status', 'approved')->get(),
        ]);
    }

    public function preview(Request $request, Client $client): View
    {
        $data = $this->validated($request);
        $numbers = $this->extractNumbers($request);
        $preview = $this->previewService->preview($client, $numbers, $data['message_body']);

        return view('client.send.preview', [
            'client' => $client,
            'data' => $data,
            'preview' => $preview,
            'numbers_raw' => implode("\n", $numbers),
            'senderIds' => SenderId::query()->where('client_id', $client->id)->where('status', 'approved')->get(),
        ]);
    }

    public function store(Request $request, Client $client): RedirectResponse
    {
        $data = $this->validated($request);
        $numbers = $this->extractNumbers($request);
        $action = $request->input('action', 'launch');

        if ($action === 'single' || count($numbers) === 1 && $action !== 'draft') {
            $message = $this->dispatch->sendSingle(
                $client,
                $request->user(),
                SenderId::query()->where('client_id', $client->id)->findOrFail($data['sender_id_id']),
                $numbers[0],
                $data['message_body'],
                MessageCategory::from($data['category']),
            );

            return redirect()
                ->route('client.campaigns.index', $client)
                ->with('success', 'SMS queued: '.$message->uuid);
        }

        $campaign = $this->dispatch->createCampaign(
            client: $client,
            user: $request->user(),
            data: $data,
            numbers: $numbers,
            launch: $action === 'launch',
            schedule: $action === 'schedule',
        );

        return redirect()
            ->route('client.campaigns.show', [$client, $campaign])
            ->with('success', 'Campaign '.$campaign->status->value.'.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sender_id_id' => ['required', 'exists:sender_ids,id'],
            'template_id' => ['nullable', 'exists:templates,id'],
            'category' => ['required', 'in:otp,transactional,alert,marketing'],
            'message_body' => ['required', 'string', 'max:1000'],
            'numbers' => ['nullable', 'string'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'confirm' => ['sometimes', 'accepted'],
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected function extractNumbers(Request $request): array
    {
        $numbers = preg_split('/[\s,;]+/', (string) $request->input('numbers', '')) ?: [];

        if ($request->hasFile('file')) {
            $rows = Excel::toArray(null, $request->file('file'))[0] ?? [];
            foreach ($rows as $i => $row) {
                if ($i === 0 && isset($row[0]) && ! preg_match('/\d/', (string) $row[0])) {
                    continue;
                }
                if (isset($row[0])) {
                    $numbers[] = (string) $row[0];
                }
            }
        }

        if ($request->filled('contact_ids')) {
            $fromContacts = $request->user()
                ? \App\Models\Contact::query()
                    ->whereIn('id', (array) $request->input('contact_ids', []))
                    ->pluck('phone_normalized')
                    ->all()
                : [];
            $numbers = array_merge($numbers, $fromContacts);
        }

        return array_values(array_filter(array_map('trim', $numbers)));
    }
}
