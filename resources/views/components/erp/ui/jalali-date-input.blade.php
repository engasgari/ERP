@props([
    'value' => null,
    'placeholder' => '1403/03/17',
    'required' => false,
])

@php
    $hasWireModel = collect($attributes->getAttributes())
        ->keys()
        ->contains(static fn (string $key): bool => str_starts_with($key, 'wire:model'));
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'erp-jalali-date-field']) }}>
    <input
        type="text"
        data-jalali-datepicker
        inputmode="numeric"
        dir="ltr"
        autocomplete="off"
        placeholder="{{ $placeholder }}"
        title="سال/ماه/روز — با کلیک تقویم شمسی باز می‌شود"
        @unless($hasWireModel)
            value="{{ $value }}"
        @endunless
        @if($required) required @endif
        {{ $attributes->except('class')->merge(['class' => 'erp-jalali-date-input w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500']) }}
    >
</div>
