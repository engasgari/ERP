@props([
    'value' => null,
    'placeholder' => '1403/03/17',
    'required' => false,
])

<input
    type="text"
    data-jalali-datepicker
    inputmode="numeric"
    dir="ltr"
    autocomplete="off"
    placeholder="{{ $placeholder }}"
    value="{{ $value }}"
    {{ $required ? 'required' : '' }}
    {{ $attributes->merge(['class' => 'erp-jalali-date-input rounded-md border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500']) }}
>
