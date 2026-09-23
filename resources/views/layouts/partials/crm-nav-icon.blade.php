@switch($icon ?? 'link')
    @case('home')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1z"/></svg>
        @break
    @case('customers')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        @break
    @case('pipeline')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="3" y="5" width="5" height="14" rx="1"/><rect x="10" y="8" width="5" height="11" rx="1"/><rect x="17" y="11" width="4" height="8" rx="1"/></svg>
        @break
    @case('opportunity')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M12 2v4"/><path d="m16.2 7.8 2.9-2.9"/><path d="M18 12h4"/><path d="m16.2 16.2 2.9 2.9"/><path d="M12 18v4"/><path d="m4.9 19.1 2.9-2.9"/><path d="M2 12h4"/><path d="m4.9 4.9 2.9 2.9"/><circle cx="12" cy="12" r="3.5"/></svg>
        @break
    @case('menu')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M4 7h16M4 12h16M4 17h10"/></svg>
        @break
    @default
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M10 13a5 5 0 0 1 7 0l1 1a5 5 0 0 1-7 7l-1-1a5 5 0 0 1 0-7z"/><path d="M14 11a5 5 0 0 0-7 0l-1 1a5 5 0 0 0 7 7l1-1a5 5 0 0 0 0-7z"/></svg>
@endswitch
