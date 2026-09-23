@props([
    'title',
    'payload' => [],
    'subtitle' => null,
    'empty' => 'داده‌ای برای نمایش نیست.',
    'ctaUrl' => null,
    'ctaLabel' => null,
])

@php
    $series = $payload['series'] ?? [];
    $points = $payload['points'] ?? [];
    $max = 1.0;
    foreach ($points as $point) {
        foreach ($point['values'] ?? [] as $value) {
            $max = max($max, abs((float) $value));
        }
    }
@endphp

<section {{ $attributes->merge(['class' => 'exec-panel exec-profit-chart']) }}>
    <header class="exec-panel__head exec-panel__head--split">
        <div>
            <h3 class="exec-section-title">{{ $title }}</h3>
            @if($subtitle)
                <p class="exec-profit-chart__subtitle">{{ $subtitle }}</p>
            @endif
        </div>
        @if($ctaUrl && $ctaLabel)
            <a href="{{ $ctaUrl }}" wire:navigate class="exec-panel__cta exec-panel__cta--inline">{{ $ctaLabel }}</a>
        @endif
    </header>

    @if($points === [] || $series === [])
        <p class="exec-bar-chart__empty">{{ $empty }}</p>
    @else
        <ul class="exec-profit-chart__legend" aria-hidden="true">
            @foreach($series as $item)
                <li class="exec-profit-chart__legend-item exec-profit-chart__legend-item--{{ $item['key'] }}">
                    {{ $item['label'] }}
                </li>
            @endforeach
        </ul>

        <div class="exec-profit-chart__plot" role="img" aria-label="{{ $title }}">
            @foreach($points as $point)
                <div class="exec-profit-chart__month">
                    <div class="exec-profit-chart__bars">
                        @foreach($series as $item)
                            @php
                                $seriesKey = $item['key'];
                                $value = (float) ($point['values'][$seriesKey] ?? 0);
                                $height = $value !== 0.0 ? max(5, min(100, (abs($value) / $max) * 100)) : 0;
                                $tooltip = ($item['label'] ?? '').': '.formatMoney($value).' ریال';
                                $isNegativeProfit = $seriesKey === 'profit' && $value < 0;
                            @endphp
                            <div
                                class="exec-profit-chart__bar exec-profit-chart__bar--{{ $seriesKey }}{{ $isNegativeProfit ? ' exec-profit-chart__bar--negative' : '' }}"
                                style="height: {{ $height }}%"
                                title="{{ $tooltip }}"
                            ></div>
                        @endforeach
                    </div>
                    <div class="exec-profit-chart__month-label">{{ $point['label'] ?? '—' }}</div>
                </div>
            @endforeach
        </div>
    @endif
</section>
