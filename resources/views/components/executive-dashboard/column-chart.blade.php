@props([
    'title',
    'points' => [],
    'subtitle' => null,
    'empty' => 'داده‌ای برای نمایش نیست.',
    'ctaUrl' => null,
    'ctaLabel' => null,
    'variant' => 'default',
])

@php
    $max = max(1.0, (float) collect($points)->max('value'));
    $isCompact = $variant === 'compact';
    $sectionClass = 'exec-panel exec-column-chart'.($isCompact ? ' exec-column-chart--compact' : '');
@endphp

<section {{ $attributes->merge(['class' => $sectionClass]) }}>
    <header class="exec-panel__head exec-panel__head--split">
        <div>
            <h3 class="exec-section-title">{{ $title }}</h3>
            @if($subtitle)
                <p class="exec-column-chart__subtitle">{{ $subtitle }}</p>
            @endif
        </div>
        @if($ctaUrl && $ctaLabel)
            <a href="{{ $ctaUrl }}" wire:navigate class="exec-panel__cta exec-panel__cta--inline">{{ $ctaLabel }}</a>
        @endif
    </header>

    @if($points === [])
        <p class="exec-bar-chart__empty">{{ $empty }}</p>
    @else
        <div class="exec-column-chart__plot" role="img" aria-label="{{ $title }}">
            @foreach($points as $point)
                @php
                    $value = (float) ($point['value'] ?? 0);
                    $height = $value > 0 ? max(8, min(100, ($value / $max) * 100)) : 0;
                    $tooltip = $point['title'] ?? formatMoney($value).' ریال';
                @endphp
                <div class="exec-column-chart__month">
                    <div class="exec-column-chart__value" title="{{ $tooltip }}">
                        <x-executive-dashboard.money
                            :amount="$value"
                            :class="$isCompact ? 'exec-money--chart exec-money--column-compact' : 'exec-money--chart'"
                        />
                    </div>
                    <div class="exec-column-chart__bars" title="{{ $tooltip }}">
                        <div class="exec-column-chart__bar" style="height: {{ $height }}%"></div>
                    </div>
                    <div class="exec-column-chart__label" title="{{ $point['label'] ?? '' }}">{{ $point['label'] ?? '—' }}</div>
                </div>
            @endforeach
        </div>
    @endif
</section>
