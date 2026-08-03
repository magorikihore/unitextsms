@props([
    'name' => 'password',
    'id' => null,
    'size' => 'md', // md = input-field, sm = input-field-sm
])

@php
    $fieldId = $id ?: $name;
    $baseClass = $size === 'sm' ? 'input-field-sm' : 'input-field';
    $wrapperClass = trim('relative '.$attributes->get('class'));
@endphp

<div class="{{ $wrapperClass }}" x-data="{ show: false }">
    <input
        {{ $attributes->except('class')->merge([
            'id' => $fieldId,
            'name' => $name,
            'type' => 'password',
            'class' => $baseClass.' w-full pr-10',
        ]) }}
        :type="show ? 'text' : 'password'"
    >
    <button
        type="button"
        class="absolute inset-y-0 right-0 flex items-center px-2.5 text-slate-400 hover:text-slate-600"
        @click="show = !show"
        :aria-label="show ? 'Hide password' : 'Show password'"
        tabindex="-1"
    >
        <span x-show="!show"><x-icon name="eye" class="h-4 w-4" /></span>
        <span x-show="show" x-cloak><x-icon name="eye-off" class="h-4 w-4" /></span>
    </button>
</div>
