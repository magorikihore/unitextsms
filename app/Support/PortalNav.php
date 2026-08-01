<?php

namespace App\Support;

use App\Models\Client;

class PortalNav
{
    public static function admin(string $active = 'dashboard'): array
    {
        $items = [
            'dashboard' => ['label' => 'Dashboard', 'href' => route('admin.dashboard')],
            'clients' => ['label' => 'Clients', 'href' => route('admin.clients.index')],
            'traffic' => ['label' => 'SMS Traffic', 'href' => route('admin.traffic.index')],
            'sender-ids' => ['label' => 'Sender IDs', 'href' => route('admin.sender-ids.index')],
            'providers' => ['label' => 'Providers & Routes', 'href' => route('admin.providers.index')],
            'credits' => ['label' => 'Credits & Billing', 'href' => route('admin.credits.index')],
            'reports' => ['label' => 'Reports', 'href' => route('admin.reports.index')],
            'approvals' => ['label' => 'Approvals', 'href' => route('admin.approvals.index')],
            'audit' => ['label' => 'Audit Logs', 'href' => route('admin.audit-logs.index')],
        ];

        return collect($items)->map(fn ($item, $key) => $item + ['active' => $key === $active])->values()->all();
    }

    public static function client(Client $client, string $active = 'dashboard'): array
    {
        $items = [
            'dashboard' => ['label' => 'Dashboard', 'href' => route('client.dashboard', $client)],
            'send' => ['label' => 'Send SMS', 'href' => route('client.send.create', $client)],
            'campaigns' => ['label' => 'Campaigns', 'href' => route('client.campaigns.index', $client)],
            'contacts' => ['label' => 'Contacts', 'href' => route('client.contacts.index', $client)],
            'templates' => ['label' => 'Templates', 'href' => route('client.templates.index', $client)],
            'reports' => ['label' => 'Reports', 'href' => route('client.reports.index', $client)],
            'api' => ['label' => 'API & Webhooks', 'href' => route('client.api.index', $client)],
            'billing' => ['label' => 'Billing', 'href' => route('client.billing.index', $client)],
            'team' => ['label' => 'Team', 'href' => route('client.team.index', $client)],
            'sender-ids' => ['label' => 'Sender IDs', 'href' => route('client.sender-ids.index', $client)],
        ];

        return collect($items)->map(fn ($item, $key) => $item + ['active' => $key === $active])->values()->all();
    }
}
