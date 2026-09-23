@props([
    'value' => null,
    'placeholder' => '1403/03/17 14:30',
    'required' => false,
])

@php
    $hasWireModel = collect($attributes->getAttributes())
        ->keys()
        ->contains(static fn (string $key): bool => str_starts_with($key, 'wire:model'));
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'relative']) }}>
    <input
        type="text"
        data-jalali-datepicker
        data-jalali-datetime
        inputmode="numeric"
        dir="ltr"
        autocomplete="off"
        placeholder="{{ $placeholder }}"
        @unless($hasWireModel)
            value="{{ $value }}"
        @endunless
        {{ $required ? 'required' : '' }}
        {{ $attributes->except('class')->merge(['class' => 'erp-jalali-date-input w-full rounded-md border-slate-300 pe-10 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500']) }}
    >
    <button
        type="button"
        tabindex="-1"
        data-jalali-datepicker-trigger
        title="انتخاب از تقویم شمسی"
        class="absolute inset-y-0 left-0 flex items-center px-2.5 text-slate-500 hover:text-teal-700"
        aria-label="باز کردن تقویم شمسی"
    >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
            <path fill-rule="evenodd" d="M6 2a1 1 0 0 1 1 1v1h6V3a1 1 0 1 1 2 0v1h1a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h1V3a1 1 0 0 1 1-1Zm11 7H3v7a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9Z" clip-rule="evenodd" />
        </svg>
    </button>
</div>
