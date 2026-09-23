@props([
    'title' => null,
    'pastel' => 'lavender',
    'href' => null,
])

@if($href)
    <a
        href="{{ $href }}"
        wire:navigate
        {{ $attributes->class(['crm-dashboard-panel', 'crm-dashboard-panel--link', 'crm-dashboard-panel--'.$pastel]) }}
    >
        @if($title)
            <h3 class="crm-dashboard-panel__title">{{ $title }}</h3>
        @endif
        {{ $slot }}
    </a>
@else
    <div {{ $attributes->class(['crm-dashboard-panel', 'crm-dashboard-panel--'.$pastel]) }}>
        @if($title)
            <h3 class="crm-dashboard-panel__title">{{ $title }}</h3>
        @endif
        {{ $slot }}
    </div>
@endif
