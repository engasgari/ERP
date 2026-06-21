@props([
    'tone' => 'danger',
    'title' => null,
    'message' => null,
    'details' => [],
])

@php
    $styles = [
        'danger' => 'border-red-200 bg-red-50 text-red-800',
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
        'info' => 'border-blue-200 bg-blue-50 text-blue-800',
    ][$tone] ?? 'border-red-200 bg-red-50 text-red-800';
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border px-4 py-3 shadow-sm ' . $styles]) }}>
    @if($title)
        <div class="text-sm font-extrabold">{{ $title }}</div>
    @endif

    @if($message)
        <div class="mt-1 text-sm font-medium leading-7">{{ $message }}</div>
    @endif

    @if(!empty($details))
        <ul class="mt-3 list-disc space-y-1 pr-5 text-sm">
            @foreach((array) $details as $detail)
                <li>{{ $detail }}</li>
            @endforeach
        </ul>
    @endif
</div>
