<?php

namespace App\Support;

use App\Models\Client;

class PortalNav
{
    public static function admin(string $active = 'dashboard'): array
    {
        $items = [
            'dashboard' => ['label' => 'Dashboard', 'icon' => 'dashboard', 'href' => route('admin.dashboard')],
            'clients' => ['label' => 'Clients', 'icon' => 'clients', 'href' => route('admin.clients.index')],
            'traffic' => ['label' => 'SMS Traffic', 'icon' => 'traffic', 'href' => route('admin.traffic.index')],
            'campaigns' => ['label' => 'Campaigns', 'icon' => 'campaigns', 'href' => route('admin.campaigns.index')],
            'sender-ids' => ['label' => 'Sender IDs', 'icon' => 'sender', 'href' => route('admin.sender-ids.index')],
            'providers' => ['label' => 'Providers & Routes', 'icon' => 'providers', 'href' => route('admin.providers.index')],
            'credits' => ['label' => 'Payments', 'icon' => 'payments', 'href' => route('admin.credits.index')],
            'reports' => ['label' => 'Reports', 'icon' => 'reports', 'href' => route('admin.reports.index')],
            'approvals' => ['label' => 'Approvals', 'icon' => 'approvals', 'href' => route('admin.approvals.index')],
            'alerts' => ['label' => 'Alerts', 'icon' => 'alerts', 'href' => route('admin.alerts.index')],
            'users' => ['label' => 'Users & Roles', 'icon' => 'users', 'href' => route('admin.users.index')],
            'settings' => ['label' => 'System Settings', 'icon' => 'settings', 'href' => route('admin.settings.index')],
            'account' => ['label' => 'Account', 'icon' => 'users', 'href' => route('admin.account.edit')],
            'audit' => ['label' => 'Audit Logs', 'icon' => 'audit', 'href' => route('admin.audit-logs.index')],
        ];

        return collect($items)->map(fn ($item, $key) => $item + ['active' => $key === $active])->values()->all();
    }

    public static function client(Client $client, string $active = 'dashboard'): array
    {
        $items = [
            'dashboard' => ['label' => 'Dashboard', 'icon' => 'dashboard', 'href' => route('client.dashboard', $client)],
            'send' => ['label' => 'Send SMS', 'icon' => 'send', 'href' => route('client.send.create', $client)],
            'campaigns' => ['label' => 'Campaigns', 'icon' => 'campaigns', 'href' => route('client.campaigns.index', $client)],
            'contacts' => ['label' => 'Contacts', 'icon' => 'contacts', 'href' => route('client.contacts.index', $client)],
            'templates' => ['label' => 'Templates', 'icon' => 'templates', 'href' => route('client.templates.index', $client)],
            'reports' => ['label' => 'Reports', 'icon' => 'reports', 'href' => route('client.reports.index', $client)],
            'alerts' => ['label' => 'Messages', 'icon' => 'alerts', 'href' => route('client.alerts.index', $client)],
            'api' => ['label' => 'API & Webhooks', 'icon' => 'api', 'href' => route('client.api.index', $client)],
            'billing' => ['label' => 'Payments', 'icon' => 'payments', 'href' => route('client.billing.index', $client)],
            'team' => ['label' => 'Team', 'icon' => 'team', 'href' => route('client.team.index', $client)],
            'sender-ids' => ['label' => 'Sender IDs', 'icon' => 'sender', 'href' => route('client.sender-ids.index', $client)],
            'settings' => ['label' => 'Settings', 'icon' => 'settings', 'href' => route('client.settings.index', $client)],
        ];

        return collect($items)->map(fn ($item, $key) => $item + ['active' => $key === $active])->values()->all();
    }

    public static function titleIcon(string $active): string
    {
        return match ($active) {
            'dashboard' => 'dashboard',
            'clients' => 'clients',
            'traffic' => 'traffic',
            'campaigns', 'send' => 'campaigns',
            'sender-ids' => 'sender',
            'providers' => 'providers',
            'credits', 'billing' => 'payments',
            'reports' => 'reports',
            'approvals' => 'approvals',
            'alerts' => 'alerts',
            'users', 'team' => 'users',
            'settings', 'account' => 'settings',
            'audit' => 'audit',
            'contacts' => 'contacts',
            'templates' => 'templates',
            'api' => 'api',
            default => 'dot',
        };
    }
}
