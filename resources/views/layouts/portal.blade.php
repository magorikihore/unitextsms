<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }} · UniNText</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="portal-shell antialiased">
<div class="flex min-h-screen">
    <aside class="hidden w-64 shrink-0 border-r border-slate-200/80 bg-slate-950 text-slate-100 lg:flex lg:flex-col">
        <div class="border-b border-white/10 px-5 py-5">
            <a href="{{ route('dashboard') }}" class="block">
                <div class="font-serif text-2xl font-bold tracking-tight text-white">UniNText</div>
                <div class="mt-1 text-xs uppercase tracking-[0.2em] text-teal-300/80">{{ $portalLabel ?? 'SMS Platform' }}</div>
            </a>
        </div>
        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4 text-sm">
            @isset($nav)
                @foreach($nav as $item)
                    <a href="{{ $item['href'] }}"
                       class="flex items-center rounded-lg px-3 py-2 transition {{ ($item['active'] ?? false) ? 'bg-teal-700 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            @endisset
        </nav>
        <div class="border-t border-white/10 px-4 py-4 text-xs text-slate-400">
            <div class="font-medium text-slate-200">{{ auth()->user()->name }}</div>
            <div>{{ auth()->user()->email }}</div>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-10 border-b border-slate-200/80 bg-white/80 backdrop-blur">
            <div class="flex items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <div>
                    <h1 class="font-serif text-2xl font-semibold text-slate-900">{{ $header ?? 'Dashboard' }}</h1>
                    @isset($subheader)
                        <p class="mt-1 text-sm text-slate-500">{{ $subheader }}</p>
                    @endisset
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn-secondary">Log out</button>
                </form>
            </div>
        </header>

        <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <ul class="list-disc ps-4">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>
