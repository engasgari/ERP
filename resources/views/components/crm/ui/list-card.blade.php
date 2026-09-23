@props([
    'title',
    'href' => null,
    'kicker' => null,
    'badgeLabel' => null,
    'badgeTone' => 'info',
])

<article {{ $attributes->merge(['class' => 'crm-list-card']) }}>
    <div class="crm-list-card__head">
        <div class="crm-list-card__titles">
            @if($kicker)
                <div class="crm-list-card__kicker">{{ $kicker }}</div>
            @endif
            @if($href)
                <a href="{{ $href }}" wire:navigate class="crm-list-card__title crm-list-card__title--link">{{ $title }}</a>
            @else
                <h3 class="crm-list-card__title">{{ $title }}</h3>
            @endif
        </div>
        @if($badgeLabel)
            <x-erp.ui.status-badge :label="$badgeLabel" :tone="$badgeTone" />
        @endif
    </div>

    @if(trim($slot) !== '')
        <dl class="crm-list-card__fields">
            {{ $slot }}
        </dl>
    @endif

    @if(isset($actions))
        <div class="crm-list-card__actions" @click.stop @mousedown.stop>
            {{ $actions }}
        </div>
    @endif
</article>
