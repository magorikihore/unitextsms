@props([
    'title' => null,
    'portalLabel' => 'SMS Platform',
    'header' => 'Dashboard',
    'subheader' => null,
    'icon' => null,
    'nav' => [],
    'navLayout' => null,
])

@php
    $navLayout = $navLayout ?? ($portalLabel === 'Administration' ? 'horizontal' : 'sidebar');
    $isHorizontal = $navLayout === 'horizontal';
    $portalClient = request()->route('client');
    if ($portalClient instanceof \App\Models\Client) {
        $portalClient->refresh();
    }
    $isPlatformViewer = auth()->user()?->isPlatformUser() && $portalClient instanceof \App\Models\Client;
    $homeUrl = $isHorizontal || auth()->user()?->isPlatformUser()
        ? route('admin.dashboard')
        : route('dashboard');
    $activeNav = collect($nav)->firstWhere('active', true);
    $pageIcon = $icon ?: ($activeNav['icon'] ?? null);
    $systemMessages = collect();
    $systemMessageCount = 0;
    if ($portalClient instanceof \App\Models\Client) {
        $systemMessages = \App\Models\SystemAlert::query()
            ->where(function ($q) use ($portalClient) {
                $q->whereNull('client_id')->orWhere('client_id', $portalClient->id);
            })
            ->where('is_resolved', false)
            ->latest()
            ->limit(6)
            ->get(['id', 'severity', 'title', 'body', 'created_at']);
        $systemMessageCount = \App\Models\SystemAlert::query()
            ->where(function ($q) use ($portalClient) {
                $q->whereNull('client_id')->orWhere('client_id', $portalClient->id);
            })
            ->where('is_resolved', false)
            ->count();
    }
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? $header }} · UniNText</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700|source-serif-4:600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="portal-shell antialiased">
<div id="ajax-progress"><div class="ajax-progress-bar"></div></div>

@if($isHorizontal)
    <div class="flex min-h-screen flex-col">
        <header class="admin-topbar">
            <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6">
                <a href="{{ $homeUrl }}" data-ajax-nav class="shrink-0">
                    <div class="text-xl font-bold tracking-tight text-white" style="font-family:'Source Serif 4', Georgia, serif">UniNText</div>
                    <div class="admin-topbar-brand-sub">Admin console</div>
                </a>
                <div class="admin-topbar-meta">
                    <div class="hidden text-right sm:block">
                        <div class="font-medium text-white">{{ auth()->user()->name }}</div>
                        <div class="truncate max-w-[12rem]">{{ auth()->user()->email }}</div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" data-ajax>
                        @csrf
                        <button type="submit" data-loading-text="…" class="admin-topbar-logout">Log out</button>
                    </form>
                </div>
            </div>
            <nav data-portal-nav class="admin-topnav">
                @foreach($nav as $item)
                    <a href="{{ $item['href'] }}"
                       data-ajax-nav
                       class="admin-topnav-link inline-flex items-center gap-1.5 {{ !empty($item['active']) ? 'is-active' : '' }}">
                        @if(!empty($item['icon']))
                            <x-icon :name="$item['icon']" class="h-3.5 w-3.5 opacity-70" />
                        @endif
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
        </header>

        <div class="border-b border-slate-200/80 bg-white/90">
            <div class="px-4 py-4 sm:px-6 lg:px-8" data-portal-header>
                <h1 class="flex items-center gap-2.5 text-2xl font-semibold text-slate-900" style="font-family:'Source Serif 4', Georgia, serif">
                    @if($pageIcon)
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-blue-400/90">
                            <x-icon :name="$pageIcon" class="h-5 w-5" />
                        </span>
                    @endif
                    <span>{{ $header }}</span>
                </h1>
                @if($subheader)
                    <p class="mt-1 text-sm text-slate-500 {{ $pageIcon ? 'ms-11.5' : '' }}" style="{{ $pageIcon ? 'margin-left: 2.875rem' : '' }}">{{ $subheader }}</p>
                @endif
            </div>
        </div>

        <main data-portal-main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
            @include('components.portal-flash')
            {{ $slot }}
        </main>
    </div>
@else
    <div class="flex min-h-screen">
        <aside class="hidden w-64 shrink-0 border-r border-slate-200/80 bg-slate-950 text-slate-100 lg:flex lg:flex-col">
            <div class="border-b border-white/10 px-5 py-5">
                <a href="{{ $isPlatformViewer ? route('admin.clients.show', $portalClient) : $homeUrl }}" data-ajax-nav class="block">
                    <div class="text-2xl font-bold tracking-tight text-white" style="font-family:'Source Serif 4', Georgia, serif">UniNText</div>
                    <div class="mt-1 text-xs uppercase tracking-[0.2em] text-teal-300/80">{{ $portalLabel }}</div>
                </a>
                @if($isPlatformViewer)
                    <a href="{{ route('admin.dashboard') }}" data-ajax-nav
                       class="mt-3 inline-flex w-full items-center justify-center rounded-md border border-amber-400/40 bg-amber-400/10 px-2 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-amber-200 hover:bg-amber-400/20">
                        ← Back to admin console
                    </a>
                @endif
            </div>
            <nav data-portal-nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4 text-sm">
                @foreach($nav as $item)
                    <a href="{{ $item['href'] }}"
                       data-ajax-nav
                       class="flex items-center gap-2.5 rounded-lg px-3 py-2 transition {{ !empty($item['active']) ? 'bg-teal-700 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                        @if(!empty($item['icon']))
                            <x-icon :name="$item['icon']" class="h-4 w-4 {{ !empty($item['active']) ? 'opacity-95' : 'opacity-45' }}" />
                        @endif
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
            @if($portalClient)
                <div class="border-t border-white/10 px-4 py-4 text-xs text-slate-400">
                    <div class="text-[10px] uppercase tracking-wide text-slate-500">SMS balance</div>
                    <div class="mt-0.5 text-lg font-semibold text-teal-300">{{ number_format((float) $portalClient->sms_balance, 2) }}</div>
                </div>
            @endif
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="portal-client-header">
                <div class="flex items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
                    <div data-portal-header class="min-w-0">
                        <h1 class="flex min-w-0 items-center gap-2.5 text-xl font-semibold text-slate-900 sm:text-2xl" style="font-family:'Source Serif 4', Georgia, serif">
                            @if($pageIcon)
                                <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-teal-50 text-teal-400/90 sm:h-9 sm:w-9">
                                    <x-icon :name="$pageIcon" class="h-4 w-4 sm:h-5 sm:w-5" />
                                </span>
                            @endif
                            <span class="truncate">{{ $header }}</span>
                        </h1>
                        @if($subheader)
                            <p class="mt-0.5 truncate text-sm text-slate-500" style="{{ $pageIcon ? 'margin-left: 2.625rem' : '' }}">{{ $subheader }}</p>
                        @endif
                    </div>

                    <div class="portal-header-actions shrink-0">
                        @if($portalClient)
                            @if(config('payin.enabled'))
                            <div
                                x-data="depositModal({
                                    storeUrl: @js(route('client.deposits.store', $portalClient)),
                                    statusUrlTemplate: @js(route('client.deposits.status', [$portalClient, 'DEPOSIT_UUID'])),
                                    csrf: @js(csrf_token()),
                                    minAmount: {{ (int) config('payin.min_amount', 100) }},
                                    pollInterval: {{ (int) config('payin.poll_interval_seconds', 5) }},
                                    successDismiss: {{ (int) config('payin.success_dismiss_seconds', 60) }},
                                    enabled: true,
                                    balance: {{ (float) $portalClient->sms_balance }},
                                })"
                            >
                                <button type="button" class="btn-sm-primary" @click="open()">Deposit</button>

                                <template x-teleport="body">
                                    <div x-show="visible" x-cloak
                                         class="fixed inset-0 z-[200] flex items-center justify-center overflow-y-auto bg-slate-900/45 p-4"
                                         role="dialog" aria-modal="true" @keydown.escape.window="tryClose()">
                                        <div class="absolute inset-0" @click="tryClose()"></div>
                                        <div class="relative z-10 my-auto w-full max-w-md rounded-xl border border-slate-200 bg-white shadow-2xl" @click.stop>
                                            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                                                <div class="text-base font-semibold text-slate-900">Deposit</div>
                                                <button type="button" class="text-2xl leading-none text-slate-400 hover:text-slate-700" @click="tryClose()" aria-label="Close">&times;</button>
                                            </div>

                                            <div class="space-y-3 px-4 py-4" x-show="step === 'form'" x-cloak>
                                                <p class="text-sm text-slate-500">Enter your mobile money number and amount. Confirm the USSD prompt on your phone.</p>
                                                <div>
                                                    <label class="mb-1 block text-xs font-medium text-slate-600">Phone number</label>
                                                    <input type="tel" class="input-field-sm w-full" x-model="phone" placeholder="07XXXXXXXX" :disabled="busy" @keydown.enter.prevent="submit()">
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-xs font-medium text-slate-600">Amount (TZS)</label>
                                                    <input type="number" min="100" step="1" class="input-field-sm w-full" x-model="amount" placeholder="5000" :disabled="busy" @keydown.enter.prevent="submit()">
                                                </div>
                                                <p class="text-xs text-red-600" x-show="error" x-text="error"></p>
                                                <div class="flex justify-end gap-2 pt-1">
                                                    <button type="button" class="btn-sm-secondary" @click="close()" :disabled="busy">Cancel</button>
                                                    <button type="button" class="btn-sm-primary min-w-[7rem]" @click="submit()" :disabled="busy">
                                                        <span x-show="!busy">Pay now</span>
                                                        <span x-show="busy">Starting…</span>
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="space-y-3 px-4 py-8 text-center" x-show="step === 'waiting'" x-cloak>
                                                <div class="mx-auto h-10 w-10 animate-spin rounded-full border-2 border-teal-600 border-t-transparent"></div>
                                                <div class="text-base font-semibold text-slate-900">Waiting for payment</div>
                                                <p class="text-sm text-slate-500">Approve the prompt on <span class="font-medium text-slate-700" x-text="phone"></span>. This screen updates automatically.</p>
                                                <p class="text-xs text-slate-400" x-show="depositRef">Ref: <span x-text="depositRef"></span></p>
                                                <p class="text-xs text-red-600" x-show="error" x-text="error"></p>
                                            </div>

                                            <div class="space-y-3 px-4 py-8 text-center" x-show="step === 'success'" x-cloak>
                                                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-teal-100 text-teal-700">
                                                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                                </div>
                                                <div class="text-base font-semibold text-slate-900">Payment successful</div>
                                                <p class="text-sm text-slate-500">
                                                    Credited <span class="font-semibold text-slate-800" x-text="formatMoney(credits)"></span>
                                                    · Balance <span class="font-semibold text-teal-800" x-text="formatMoney(balance)"></span>
                                                </p>
                                                <p class="text-xs text-slate-400">This window closes in <span x-text="dismissLeft"></span>s</p>
                                            </div>

                                            <div class="space-y-3 px-4 py-8 text-center" x-show="step === 'failed'" x-cloak>
                                                <div class="text-base font-semibold text-slate-900">Payment not completed</div>
                                                <p class="text-sm text-slate-500" x-text="error || 'The payment failed or expired.'"></p>
                                                <button type="button" class="btn-sm-primary" @click="resetForm()">Try again</button>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            @endif

                            <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
                                <button type="button"
                                        class="portal-header-icon"
                                        @click="open = !open"
                                        :aria-expanded="open.toString()"
                                        title="System messages">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                                    </svg>
                                    @if($systemMessageCount > 0)
                                        <span class="portal-header-badge">{{ $systemMessageCount > 9 ? '9+' : $systemMessageCount }}</span>
                                    @endif
                                </button>
                                <div x-show="open" x-cloak @click.outside="open = false"
                                     class="absolute right-0 z-30 mt-2 w-80 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl sm:w-96">
                                    <div class="flex items-center justify-between border-b border-slate-100 px-3 py-2">
                                        <div class="text-sm font-semibold text-slate-900">System messages</div>
                                        <a href="{{ route('client.alerts.index', $portalClient) }}" data-ajax-nav class="text-xs font-medium text-teal-700 hover:underline">View all</a>
                                    </div>
                                    <div class="max-h-80 overflow-y-auto">
                                        @forelse($systemMessages as $alert)
                                            <a href="{{ route('client.alerts.index', $portalClient) }}" data-ajax-nav
                                               class="block border-b border-slate-50 px-3 py-2.5 hover:bg-slate-50">
                                                <div class="flex items-center gap-2">
                                                    <span class="badge text-[10px]
                                                        {{ $alert->severity === 'critical' ? 'bg-red-100 text-red-800' : ($alert->severity === 'warning' ? 'bg-amber-100 text-amber-900' : 'bg-slate-100 text-slate-700') }}
                                                    ">{{ $alert->severity }}</span>
                                                    <span class="text-[11px] text-slate-400">{{ $alert->created_at->diffForHumans() }}</span>
                                                </div>
                                                <div class="mt-1 text-sm font-medium text-slate-900">{{ $alert->title }}</div>
                                                <div class="mt-0.5 line-clamp-2 text-xs text-slate-500">{{ $alert->body }}</div>
                                            </a>
                                        @empty
                                            <div class="px-3 py-6 text-center text-sm text-slate-500">No system messages</div>
                                        @endforelse
                                    </div>
                                </div>
                            </div>

                            <div class="portal-header-balance" title="SMS balance">
                                <span class="portal-header-balance-label">Balance</span>
                                <span class="portal-header-balance-value" data-portal-balance>{{ number_format((float) $portalClient->sms_balance, 2) }}</span>
                            </div>
                        @endif

                        <div class="portal-header-user">
                            <div class="truncate font-medium text-slate-900">{{ auth()->user()->name }}</div>
                            <div class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</div>
                        </div>

                        <form method="POST" action="{{ route('logout') }}" data-ajax>
                            @csrf
                            <button type="submit" data-loading-text="…" class="btn-sm-secondary">Sign out</button>
                        </form>
                    </div>
                </div>
            </header>

            <main data-portal-main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                @if($isPlatformViewer)
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950">
                        <span>Viewing <strong>{{ $portalClient->company_name }}</strong> as platform admin (client portal).</span>
                        <a href="{{ route('admin.dashboard') }}" data-ajax-nav class="font-semibold text-amber-800 underline hover:no-underline">Admin console</a>
                    </div>
                @endif
                @include('components.portal-flash')
                {{ $slot }}
            </main>
        </div>
    </div>
@endif
</body>
</html>
