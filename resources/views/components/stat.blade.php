@props(['label', 'value', 'hint' => null, 'tone' => 'default', 'icon' => null, 'tint' => null])

@php
$tones = [
    'default' => 'text-slate-800',
    'ok' => 'text-emerald-700',
    'warn' => 'text-amber-700',
    'bad' => 'text-rose-700',
];
$iconTone = [
    'default' => 'text-slate-300',
    'ok' => 'text-emerald-300',
    'warn' => 'text-amber-300',
    'bad' => 'text-rose-300',
];
$tintFromTone = [
    'default' => 'sky',
    'ok' => 'emerald',
    'warn' => 'amber',
    'bad' => 'rose',
];
$resolvedTint = $tint ?: ($tintFromTone[$tone] ?? 'sky');
$tintClass = match ($resolvedTint) {
    'teal' => 'stat-tile-teal',
    'emerald' => 'stat-tile-emerald',
    'amber' => 'stat-tile-amber',
    'rose' => 'stat-tile-rose',
    'violet' => 'stat-tile-violet',
    'slate' => 'stat-tile-slate',
    default => 'stat-tile-sky',
};
@endphp

<div {{ $attributes->merge(['class' => 'stat-tile '.$tintClass]) }}>
    <div class="flex items-start justify-between gap-2">
        <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $label }}</div>
        @if($icon)
            <span class="{{ $iconTone[$tone] ?? $iconTone['default'] }}">
                <x-icon :name="$icon" class="h-4 w-4" />
            </span>
        @endif
    </div>
    <div class="mt-2 text-2xl font-semibold {{ $tones[$tone] ?? $tones['default'] }}">{{ $value }}</div>
    @if($hint)
        <div class="mt-1 text-xs text-slate-500">{{ $hint }}</div>
    @endif
</div>
