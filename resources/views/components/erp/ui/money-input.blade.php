@props([
    'value' => null,
    'decimals' => 0,
])

@php
    $hasWireModel = collect($attributes->getAttributes())
        ->keys()
        ->contains(static fn (string $key): bool => str_starts_with($key, 'wire:model'));
@endphp

<input
    type="text"
    data-erp-money="1"
    inputmode="decimal"
    dir="ltr"
    autocomplete="off"
    @unless($hasWireModel)
        value="{{ moneyInputValue($value, (int) $decimals) }}"
    @endunless
    {{ $attributes->merge(['class' => 'erp-amount w-full rounded-md border-slate-300 text-sm tabular-nums shadow-sm focus:border-teal-500 focus:ring-teal-500']) }}
>
