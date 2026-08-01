<?php

namespace App\Http\Controllers\Client;

use App\Enums\MessageCategory;
use App\Enums\TemplateStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Template;
use App\Services\AuditLogger;
use App\Services\SmsCounter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TemplateController extends Controller
{
    public function __construct(
        private readonly SmsCounter $smsCounter,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Client $client): View
    {
        return view('client.templates.index', [
            'client' => $client,
            'templates' => Template::query()->where('client_id', $client->id)->latest()->paginate(20),
        ]);
    }

    public function store(Request $request, Client $client): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(MessageCategory::class)],
            'language' => ['required', 'in:en,sw'],
            'body' => ['required', 'string', 'max:1000'],
        ]);

        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $data['body'], $matches);

        $template = Template::query()->create([
            ...$data,
            'client_id' => $client->id,
            'variables' => array_values(array_unique($matches[1] ?? [])),
            'status' => TemplateStatus::PendingApproval,
        ]);

        $this->auditLogger->log('template.created', $template, null, $template->toArray(), $request->user(), $client->id);

        return back()->with('success', 'Template submitted for approval ('.$this->smsCounter->units($data['body']).' SMS unit(s)).');
    }
}
