@props([
    'emptyMessage' => 'رکوردی یافت نشد.',
    'hasItems' => false,
])

<div {{ $attributes->merge(['class' => 'crm-responsive-list']) }}>
    @if(! $hasItems)
        <div class="crm-list-empty">{{ $emptyMessage }}</div>
    @else
        <div class="crm-list-cards">
            {{ $cards ?? '' }}
        </div>
        <div class="crm-list-table">
            {{ $table ?? $slot }}
        </div>
    @endif
</div>
