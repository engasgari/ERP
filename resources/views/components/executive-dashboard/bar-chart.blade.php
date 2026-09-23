@props([
    'title',
    'points' => [],
    'subtitle' => null,
    'empty' => 'داده‌ای برای نمایش نیست.',
    'ctaUrl' => null,
    'ctaLabel' => null,
    'variant' => 'default',
    'showValue' => true,
])

@php
    $max = collect($points)->max('value') ?: 1;
    $isCategory = $variant === 'category';
    $sectionClass = 'exec-panel exec-bar-chart'.($isCategory ? ' exec-bar-chart--category' : '');
@endphp

<section {{ $attributes->merge(['class' => $sectionClass]) }}>
    <header class="exec-panel__head exec-panel__head--split">
        <div>
            <h3 class="exec-section-title">{{ $title }}</h3>
            @if($subtitle)
                <p class="exec-bar-chart__subtitle">{{ $subtitle }}</p>
            @endif
        </div>
        @if($ctaUrl && $ctaLabel)
            <a href="{{ $ctaUrl }}" wire:navigate class="exec-panel__cta exec-panel__cta--inline">{{ $ctaLabel }}</a>
        @endif
    </header>

    <div class="exec-bar-chart__rows">
        @forelse($points as $point)
            <div class="exec-bar-chart__row">
                @if($isCategory)
                    <div class="exec-bar-chart__headline">
                        <span class="exec-bar-chart__label">{{ $point['label'] ?? '—' }}</span>
                        <x-executive-dashboard.money
                            :amount="$point['value'] ?? 0"
                            class="exec-money--category-inline"
                        />
                    </div>
                    <div class="exec-bar-track exec-bar-chart__track">
                        <div
                            class="exec-bar-fill exec-bar-chart__fill"
                            style="width: {{ min(100, ((float) ($point['value'] ?? 0) / $max) * 100) }}%"
                        ></div>
                    </div>
                @else
                    <div class="exec-bar-chart__label">{{ $point['label'] ?? '—' }}</div>
                    <div class="exec-bar-track exec-bar-chart__track">
                        <div
                            class="exec-bar-fill exec-bar-chart__fill"
                            style="width: {{ min(100, ((float) ($point['value'] ?? 0) / $max) * 100) }}%"
                        ></div>
                    </div>
                    @if($showValue)
                        <div class="exec-bar-chart__value">
                            <x-executive-dashboard.money :amount="$point['value'] ?? 0" class="exec-money--chart" />
                        </div>
                    @endif
                @endif
            </div>
        @empty
            <p class="exec-bar-chart__empty">{{ $empty }}</p>
        @endforelse
    </div>
</section>
