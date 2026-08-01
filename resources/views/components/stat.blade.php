@props(['label', 'value', 'hint' => null, 'tone' => 'default'])

@php
$tones = [
    'default' => 'text-slate-900',
    'ok' => 'text-emerald-700',
    'warn' => 'text-amber-700',
    'bad' => 'text-red-700',
];
@endphp

<div {{ $attributes->merge(['class' => 'stat-tile']) }}>
    <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $label }}</div>
    <div class="mt-2 text-2xl font-semibold {{ $tones[$tone] ?? $tones['default'] }}">{{ $value }}</div>
    @if($hint)
        <div class="mt-1 text-xs text-slate-500">{{ $hint }}</div>
    @endif
</div>
