<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Contact;
use App\Models\ImportBatch;
use App\Services\ContactImportService;
use App\Services\PhoneNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function __construct(
        private readonly ContactImportService $importService,
        private readonly PhoneNormalizer $phoneNormalizer,
    ) {}

    public function index(Client $client): View
    {
        return view('client.contacts.index', [
            'client' => $client,
            'contacts' => Contact::query()->where('client_id', $client->id)->latest()->paginate(30),
            'imports' => ImportBatch::query()->where('client_id', $client->id)->latest()->limit(10)->get(),
        ]);
    }

    public function store(Request $request, Client $client): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'first_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email'],
        ]);

        $normalized = $this->phoneNormalizer->normalize($data['phone']);
        if (! $normalized) {
            return back()->withErrors(['phone' => 'Invalid Tanzanian mobile number.']);
        }

        Contact::query()->updateOrCreate(
            ['client_id' => $client->id, 'phone_normalized' => $normalized],
            [
                'phone' => $data['phone'],
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'] ?? null,
                'is_valid' => true,
                'consent_status' => 'explicit',
            ]
        );

        return back()->with('success', 'Contact saved.');
    }

    public function import(Request $request, Client $client): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls'],
        ]);

        $batch = $this->importService->import($client, $request->user(), $request->file('file'));

        return back()->with('success', "Import complete: {$batch->imported} imported, {$batch->duplicates} duplicates, {$batch->invalid} invalid.");
    }
}
