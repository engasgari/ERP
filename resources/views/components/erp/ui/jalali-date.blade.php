@props([
    'value' => null,
    'fallback' => '-',
    'datetime' => false,
])
<span {{ $attributes->class(['erp-jalali-date']) }}>
    {{ $datetime ? formatJalaliDateTime($value, $fallback) : formatJalaliDateSafe($value, $fallback) }}
</span>
