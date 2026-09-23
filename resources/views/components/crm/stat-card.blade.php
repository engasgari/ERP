@props([
    'label',
    'value',
    'pastel' => 'sky',
    'href' => null,
])

@if($href)
    <a
        href="{{ $href }}"
        wire:navigate
        {{ $attributes->class(['crm-stat-card', 'crm-stat-card--link', 'crm-stat-card--'.$pastel]) }}
    >
        <div class="crm-stat-card__label">{{ $label }}</div>
        <div class="crm-stat-card__value">{{ $value }}</div>
    </a>
@else
    <div {{ $attributes->class(['crm-stat-card', 'crm-stat-card--'.$pastel]) }}>
        <div class="crm-stat-card__label">{{ $label }}</div>
        <div class="crm-stat-card__value">{{ $value }}</div>
    </div>
@endif
