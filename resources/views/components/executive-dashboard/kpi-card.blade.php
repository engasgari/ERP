@props(['kpi', 'stagger' => 0])

@php
    $value = (float) ($kpi['value'] ?? 0);
    $format = $kpi['format'] ?? 'money';
    if ($format === 'number') {
        $display = number_format($value);
    } else {
        $display = formatMoney($value);
    }
    $delta = $kpi['delta_percent'] ?? null;
    $direction = $kpi['delta_direction'] ?? 'flat';
    $url = $kpi['url'] ?? null;
    $key = $kpi['key'] ?? 'default';
    $deltaClass = match ($direction) {
        'up' => 'exec-kpi-card__delta--up',
        'down' => 'exec-kpi-card__delta--down',
        default => 'exec-kpi-card__delta--flat',
    };
    $staggerMs = max(0, (int) $stagger) * 85;
@endphp

@if($url)
    <a
        href="{{ $url }}"
        wire:navigate
        class="exec-kpi-card exec-kpi-card--{{ $key }} exec-kpi-card--link exec-animate-kpi"
        style="--exec-stagger: {{ $staggerMs }}ms"
    >
@else
    <article
        class="exec-kpi-card exec-kpi-card--{{ $key }} exec-animate-kpi"
        style="--exec-stagger: {{ $staggerMs }}ms"
    >
@endif
    <div class="exec-kpi-card__top">
        <span class="exec-kpi-card__label">{{ $kpi['title'] ?? '' }}</span>
    </div>
    <div class="exec-kpi-card__value">
        @if($format === 'money')
            <x-executive-dashboard.money :amount="$value" class="exec-kpi-card__money" />
        @else
            <span class="exec-kpi-card__amount">{{ $display }}</span>
        @endif
    </div>
    @if($delta !== null)
        @php
            $deltaTrend = match ($direction) {
                'up' => 'رشد',
                'down' => 'ریزش',
                default => 'ثابت',
            };
        @endphp
        <div class="exec-kpi-card__delta {{ $deltaClass }}">
            <span class="exec-kpi-card__delta-trend">{{ $deltaTrend }}</span>
            <span class="exec-kpi-card__delta-value">{{ number_format(abs($delta), 1) }}٪</span>
            <span class="exec-kpi-card__delta-period">دوره قبل</span>
        </div>
    @endif
@if($url)
    </a>
@else
    </article>
@endif
