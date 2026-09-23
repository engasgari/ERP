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
    $action = $kpi['action'] ?? null;
    $alpineClick = $kpi['alpine_click'] ?? null;
    $key = $kpi['key'] ?? 'default';
    $deltaClass = match ($direction) {
        'up' => 'exec-kpi-card__delta--up',
        'down' => 'exec-kpi-card__delta--down',
        default => 'exec-kpi-card__delta--flat',
    };
    $staggerMs = max(0, (int) $stagger) * 85;
    $cardClass = 'exec-kpi-card exec-kpi-card--'.$key.' exec-animate-kpi';
    if ($url || $action || $alpineClick) {
        $cardClass .= ' exec-kpi-card--link';
    }
@endphp

@if($alpineClick)
    <button
        type="button"
        @click="{{ $alpineClick }}"
        class="{{ $cardClass }}"
        style="--exec-stagger: {{ $staggerMs }}ms"
    >
@elseif($action)
    <button
        type="button"
        wire:click="{{ $action }}"
        class="{{ $cardClass }}"
        style="--exec-stagger: {{ $staggerMs }}ms"
    >
@elseif($url)
    <a
        href="{{ $url }}"
        wire:navigate
        class="{{ $cardClass }}"
        style="--exec-stagger: {{ $staggerMs }}ms"
    >
@else
    <article
        class="{{ $cardClass }}"
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
@if($alpineClick || $action)
    </button>
@elseif($url)
    </a>
@else
    </article>
@endif
