<?php

namespace App\Http\Controllers\Client;

use App\Enums\SenderIdStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\SenderId;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SenderIdController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Client $client): View
    {
        return view('client.settings.sender-ids', [
            'client' => $client,
            'senderIds' => SenderId::query()->where('client_id', $client->id)->latest()->get(),
        ]);
    }

    public function store(Request $request, Client $client): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'sender' => ['required', 'string', 'max:11', 'alpha_num'],
        ]);

        $senderId = SenderId::query()->create([
            'client_id' => $client->id,
            'sender' => strtoupper($data['sender']),
            'category' => null,
            'status' => SenderIdStatus::Pending,
        ]);

        $this->auditLogger->log('sender_id.submitted', $senderId, null, $senderId->toArray(), $request->user(), $client->id);

        return $this->ajaxRedirect($request, route('client.sender-ids.index', $client), 'Sender ID submitted for review.');
    }
}
