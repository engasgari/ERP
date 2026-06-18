@props(['label', 'tone' => 'neutral'])
@php
    $classes = [
        'success' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'danger' => 'bg-red-50 text-red-700 border-red-200',
        'warning' => 'bg-amber-50 text-amber-700 border-amber-200',
        'info' => 'bg-blue-50 text-blue-700 border-blue-200',
        'neutral' => 'bg-slate-50 text-slate-700 border-slate-200',
    ][$tone] ?? 'bg-slate-50 text-slate-700 border-slate-200';
@endphp
<span class="inline-flex rounded-full border px-2 py-1 text-xs font-bold {{ $classes }}">{{ $label }}</span>
