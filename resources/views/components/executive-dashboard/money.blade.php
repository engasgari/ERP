@props(['amount', 'class' => ''])

<span {{ $attributes->merge(['class' => 'exec-money '.$class]) }}>
    <span class="exec-money__amount">{{ formatMoney((float) $amount) }}</span>
    <span class="exec-money__unit">ریال</span>
</span>
