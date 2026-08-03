<?php

namespace App\Http\Controllers\Client;

use App\Enums\MessageCategory;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Contact;
use App\Models\SenderId;
use App\Models\Template;
use App\Services\RecipientPreviewService;
use App\Services\SmsDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Throwable;

class SendSmsController extends Controller
{
    public function __construct(
        private readonly RecipientPreviewService $previewService,
        private readonly SmsDispatchService $dispatch,
    ) {}

    public function create(Client $client): View
    {
        $contacts = Contact::query()
            ->where('client_id', $client->id)
            ->where('is_opted_out', false)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->orderBy('phone_normalized')
            ->limit(500)
            ->get(['id', 'phone_normalized', 'first_name', 'last_name']);

        $senderIds = SenderId::query()->where('client_id', $client->id)->where('status', 'approved')->get();
        $defaultSenderId = $client->default_sender_id_id
            && $senderIds->contains('id', $client->default_sender_id_id)
            ? (string) $client->default_sender_id_id
            : '';

        return view('client.send.create', [
            'client' => $client,
            'senderIds' => $senderIds,
            'defaultSenderId' => $defaultSenderId,
            'templates' => Template::query()->where('client_id', $client->id)->where('status', 'approved')->get(),
            'contacts' => $contacts,
        ]);
    }

    public function preview(Request $request, Client $client): View|JsonResponse
    {
        $data = $this->validated($request);
        $numbers = $this->extractNumbers($request, $client);

        if ($numbers === []) {
            throw ValidationException::withMessages([
                'numbers' => 'Add at least one recipient number, pick a saved contact, or upload a file.',
            ]);
        }

        if (($data['send_mode'] ?? 'single') === 'single' && count($numbers) > 1) {
            throw ValidationException::withMessages([
                'numbers' => 'Single SMS accepts one recipient. Switch to Bulk SMS for multiple numbers.',
            ]);
        }

        $preview = $this->previewService->preview($client, $numbers, $data['message_body']);
        $payload = $this->previewPayload($preview);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'data' => $data,
                'numbers_raw' => implode("\n", $numbers),
                'preview' => $payload,
                'can_launch' => $payload['remaining_balance'] >= 0 && $payload['valid_count'] > 0,
                'can_schedule' => ! empty($data['scheduled_at']) && $payload['valid_count'] > 0,
            ]);
        }

        return view('client.send.preview', [
            'client' => $client,
            'data' => $data,
            'preview' => $preview,
            'numbers_raw' => implode("\n", $numbers),
            'senderIds' => SenderId::query()->where('client_id', $client->id)->where('status', 'approved')->get(),
        ]);
    }

    public function store(Request $request, Client $client): RedirectResponse|JsonResponse
    {
        $data = $this->validated($request);
        $numbers = $this->extractNumbers($request, $client);
        $action = $request->input('action', 'launch');
        $mode = $data['send_mode'] ?? 'single';

        if ($numbers === [] && $action !== 'draft') {
            throw ValidationException::withMessages([
                'numbers' => 'Add at least one recipient number, pick a saved contact, or upload a file.',
            ]);
        }

        if ($mode === 'single' && $action !== 'draft' && count($numbers) > 1) {
            throw ValidationException::withMessages([
                'numbers' => 'Single SMS accepts one recipient. Switch to Bulk SMS for multiple numbers.',
            ]);
        }

        try {
            $useSingle = $mode === 'single'
                || $action === 'single'
                || (count($numbers) === 1 && $action !== 'draft' && $action !== 'schedule');

            if ($useSingle && $action !== 'draft' && $action !== 'schedule') {
                $message = $this->dispatch->sendSingle(
                    $client,
                    $request->user(),
                    SenderId::query()->where('client_id', $client->id)->findOrFail($data['sender_id_id']),
                    $numbers[0],
                    $data['message_body'],
                    MessageCategory::normalize($data['category']),
                );

                $client->refresh();
                $summary = [
                    'type' => 'single',
                    'action' => 'single',
                    'title' => 'SMS queued successfully',
                    'name' => $data['name'],
                    'status' => $message->status->value,
                    'message_uuid' => $message->uuid,
                    'to' => $message->to_phone_normalized,
                    'from' => $message->from_sender,
                    'units' => $message->units,
                    'charged' => (float) $message->client_charge,
                    'balance' => (float) $client->sms_balance,
                    'valid_count' => 1,
                    'invalid_count' => 0,
                    'duplicate_count' => 0,
                    'opted_out_count' => 0,
                    'campaign_url' => route('client.campaigns.index', $client),
                ];

                return $this->sendResponse($request, route('client.campaigns.index', $client), $summary);
            }

            $campaign = $this->dispatch->createCampaign(
                client: $client,
                user: $request->user(),
                data: $data,
                numbers: $numbers,
                launch: $action === 'launch',
                schedule: $action === 'schedule',
            );

            $client->refresh();
            $summary = [
                'type' => 'campaign',
                'action' => $action,
                'title' => match ($action) {
                    'schedule' => 'Campaign scheduled',
                    'draft' => 'Draft saved',
                    default => 'Bulk SMS launched successfully',
                },
                'name' => $campaign->name,
                'status' => $campaign->status->value,
                'valid_count' => (int) $campaign->valid_count,
                'invalid_count' => (int) $campaign->invalid_count,
                'duplicate_count' => (int) $campaign->duplicate_count,
                'opted_out_count' => (int) $campaign->opted_out_count,
                'estimated_units' => (int) $campaign->estimated_units,
                'charged' => (float) ($campaign->charged_amount ?: 0),
                'estimated_cost' => (float) $campaign->estimated_cost,
                'balance' => (float) $client->sms_balance,
                'campaign_url' => route('client.campaigns.show', [$client, $campaign]),
            ];

            return $this->sendResponse(
                $request,
                route('client.campaigns.show', [$client, $campaign]),
                $summary,
            );
        } catch (RuntimeException $e) {
            return $this->ajaxBack($request, $e->getMessage(), [
                'form' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            report($e);

            return $this->ajaxBack($request, 'Unable to send right now. Please try again.', [
                'form' => 'Unable to send right now. Please try again.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sender_id_id' => ['required', 'exists:sender_ids,id'],
            'template_id' => ['nullable', 'exists:templates,id'],
            'send_mode' => ['nullable', 'in:single,bulk'],
            'category' => ['required', Rule::in(['otp', 'transactional', 'alert', 'marketing', 'bulksms', 'bulk', 'trxn'])],
            'message_body' => ['required', 'string', 'max:1000'],
            'numbers' => ['nullable', 'string'],
            'contact_ids' => ['nullable', 'array'],
            'contact_ids.*' => ['integer'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ]);

        $mode = $data['send_mode'] ?? null;
        $category = MessageCategory::normalize($data['category']);

        if ($mode === null) {
            // Infer from category when older forms omit send_mode.
            $mode = $category === MessageCategory::Marketing ? 'bulk' : 'single';
        }

        if ($mode === 'bulk') {
            $category = MessageCategory::Marketing;
        }

        $data['send_mode'] = $mode;
        $data['category'] = $category->value;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $preview
     * @return array<string, mixed>
     */
    protected function previewPayload(array $preview): array
    {
        return [
            'valid_count' => count($preview['valid']),
            'invalid_count' => count($preview['invalid']),
            'duplicates_removed' => $preview['duplicates_removed'],
            'opted_out_count' => count($preview['opted_out']),
            'estimated_units' => $preview['estimated_units'],
            'estimated_cost' => $preview['estimated_cost'],
            'remaining_balance' => $preview['remaining_balance'],
            'estimated_completion_seconds' => $preview['estimated_completion_seconds'],
            'invalid_sample' => array_slice($preview['invalid'], 0, 8),
            'opted_out_sample' => array_slice($preview['opted_out'], 0, 8),
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    protected function sendResponse(Request $request, string $url, array $summary): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => $summary['title'],
                'summary' => $summary,
                'redirect' => null,
            ]);
        }

        return redirect()->to($url)->with('success', $summary['title']);
    }

    /**
     * @return array<int, string>
     */
    protected function extractNumbers(Request $request, Client $client): array
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

        $contactIds = array_values(array_filter(array_map('intval', (array) $request->input('contact_ids', []))));
        if ($contactIds !== []) {
            $fromContacts = Contact::query()
                ->where('client_id', $client->id)
                ->whereIn('id', $contactIds)
                ->where('is_opted_out', false)
                ->pluck('phone_normalized')
                ->all();
            $numbers = array_merge($numbers, $fromContacts);
        }

        return array_values(array_filter(array_map('trim', $numbers)));
    }
}
