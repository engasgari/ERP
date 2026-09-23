@props([
    'value' => null,
    'decimals' => 0,
    'suffix' => '',
])
<span {{ $attributes->class(['erp-amount']) }}>{{ formatMoney($value, (int) $decimals) }}{{ $suffix }}</span>
