<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Sign in' }} · UniNText</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700|source-serif-4:600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="portal-shell antialiased">
    <div id="ajax-progress"><div class="ajax-progress-bar"></div></div>

    <div class="relative flex min-h-screen">
        <div class="absolute inset-0 -z-10 overflow-hidden">
            <div class="absolute -left-20 top-0 h-[28rem] w-[28rem] rounded-full bg-teal-500/15 blur-3xl"></div>
            <div class="absolute bottom-0 right-0 h-[24rem] w-[24rem] rounded-full bg-orange-400/10 blur-3xl"></div>
            <div class="absolute inset-0 opacity-[0.035]" style="background-image:radial-gradient(#0f172a 1px, transparent 1px);background-size:22px 22px;"></div>
        </div>

        <section class="relative hidden w-[46%] flex-col justify-between overflow-hidden bg-slate-950 px-12 py-12 text-white lg:flex">
            <div>
                <div class="font-semibold tracking-tight" style="font-family:'Source Serif 4', Georgia, serif; font-size:2.4rem;">UniNText</div>
                <p class="mt-2 text-sm uppercase tracking-[0.22em] text-teal-300/80">SMS Operations Platform</p>
            </div>
            <div class="max-w-md">
                <h2 class="text-3xl font-semibold leading-tight" style="font-family:'Source Serif 4', Georgia, serif;">
                    Reliable OTP, transactional and bulk messaging.
                </h2>
                <p class="mt-4 text-sm leading-relaxed text-slate-300">
                    Sign in to manage sender IDs, campaigns, credits and delivery reports from one professional workspace.
                </p>
            </div>
            <p class="text-xs text-slate-500">© {{ date('Y') }} UniNText · sms.triptz.net</p>
        </section>

        <section class="flex w-full flex-1 items-center justify-center px-4 py-10 sm:px-8">
            <div class="w-full max-w-md">
                <div class="mb-8 lg:hidden">
                    <div class="text-3xl font-semibold text-slate-900" style="font-family:'Source Serif 4', Georgia, serif;">UniNText</div>
                    <p class="mt-1 text-xs uppercase tracking-[0.2em] text-teal-700">SMS Platform</p>
                </div>

                <div class="rounded-2xl border border-slate-200/80 bg-white/90 p-7 shadow-xl shadow-slate-900/5 backdrop-blur">
                    {{ $slot }}
                </div>
            </div>
        </section>
    </div>
</body>
</html>
