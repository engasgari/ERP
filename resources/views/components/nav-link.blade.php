@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center rounded-md bg-white px-3 py-1.5 text-xs font-semibold text-teal-700 shadow-sm ring-1 ring-slate-200 transition'
            : 'inline-flex items-center rounded-md px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-white hover:text-slate-900';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
