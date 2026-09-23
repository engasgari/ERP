@props(['shortcuts' => []])

@if(count($shortcuts) > 0)
    <section class="crm-dashboard-actions" aria-label="دسترسی سریع">
        <div class="crm-dashboard-shortcuts">
            @foreach($shortcuts as $shortcut)
                <a
                    href="{{ $shortcut['url'] }}"
                    wire:navigate
                    @class([
                        'crm-dashboard-shortcut',
                        'crm-dashboard-shortcut--' . ($shortcut['tone'] ?? 'sky'),
                    ])
                >
                    <span @class([
                        'crm-dashboard-shortcut__icon',
                        'crm-dashboard-shortcut__icon--' . ($shortcut['icon'] ?? 'link'),
                    ]) aria-hidden="true">
                        @switch($shortcut['icon'] ?? 'link')
                            @case('lead')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                @break
                            @case('opportunity')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4"/><path d="m16.2 7.8 2.9-2.9"/><path d="M18 12h4"/><path d="m16.2 16.2 2.9 2.9"/><path d="M12 18v4"/><path d="m4.9 19.1 2.9-2.9"/><path d="M2 12h4"/><path d="m4.9 4.9 2.9 2.9"/><circle cx="12" cy="12" r="4"/></svg>
                                @break
                            @case('warranty')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 5-3 8-7 10-4-2-7-5-7-10V6z"/><path d="M9 12l2 2 4-5"/></svg>
                                @break
                            @default
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                        @endswitch
                    </span>
                    <span class="crm-dashboard-shortcut__label">{{ $shortcut['label'] }}</span>
                </a>
            @endforeach
        </div>
    </section>
@endif
