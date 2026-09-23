@php
    $brandRoute = $brandRoute ?? route('dashboard');
    $brandMark = $brandMark ?? 'ERP';
    $brandTitle = $brandTitle ?? 'مدیریت ERP';
@endphp

<nav class="erp-top-nav" aria-label="منوی اصلی">
    <div class="erp-nav-inner">
        <a href="{{ $brandRoute }}" wire:navigate class="erp-brand">
            <span class="erp-brand-mark">{{ $brandMark }}</span>
            <span class="erp-brand-title">{{ $brandTitle }}</span>
        </a>

        <div class="erp-nav-menu">
            @foreach ($navLinks as $link)
                @php
                    $patterns = (array) ($link['active'] ?? []);
                    $isActive = collect($patterns)->contains(fn ($pattern) => request()->routeIs($pattern));
                    $children = collect($link['children'] ?? []);
                    $parentUrl = Route::has($link['route'] ?? '') ? route($link['route'], $link['params'] ?? []) : '#';
                @endphp

                @if ($children->isNotEmpty())
                    <details class="erp-nav-item {{ $isActive ? 'is-active' : '' }}">
                        <summary class="erp-nav-link"><span>{{ $link['label'] }}</span></summary>
                        <div class="erp-nav-dropdown">
                            @foreach ($children as $child)
                                @if (($child['type'] ?? null) === 'divider')
                                    @if (! empty($child['label']))
                                        <span class="erp-nav-dropdown-divider">{{ $child['label'] }}</span>
                                    @else
                                        <span class="erp-nav-dropdown-divider"></span>
                                    @endif
                                @else
                                    <a href="{{ route($child['route'], $child['params'] ?? []) }}" wire:navigate class="erp-nav-dropdown-link">{{ $child['label'] }}</a>
                                @endif
                            @endforeach
                        </div>
                    </details>
                @else
                    <a href="{{ $parentUrl }}" wire:navigate class="erp-nav-link {{ $isActive ? 'is-active' : '' }}">{{ $link['label'] }}</a>
                @endif
            @endforeach
        </div>

        <div class="erp-nav-user">
            @auth
                @if(Route::has('crm.search.index'))
                    <a href="{{ route('crm.search.index') }}" wire:navigate class="erp-user-link">جستجو</a>
                @endif
                <a href="{{ route('profile.edit') }}" wire:navigate class="erp-user-link">{{ Auth::user()->name }}</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="erp-logout">خروج</button>
                </form>
            @endauth
        </div>
    </div>
</nav>

<script>
    (() => {
        const bindTopNav = () => {
            const nav = document.querySelector('.erp-top-nav');
            if (!nav || nav.dataset.dropdownBound === '1') {
                return;
            }

            nav.dataset.dropdownBound = '1';

            const dropdowns = Array.from(nav.querySelectorAll('.erp-nav-item'));

            dropdowns.forEach((item) => {
                item.addEventListener('toggle', () => {
                    if (!item.open) {
                        return;
                    }

                    dropdowns.forEach((other) => {
                        if (other !== item) {
                            other.open = false;
                        }
                    });
                });
            });

            document.addEventListener('click', (event) => {
                if (!nav.contains(event.target)) {
                    dropdowns.forEach((item) => {
                        item.open = false;
                    });
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    dropdowns.forEach((item) => {
                        item.open = false;
                    });
                }
            });
        };

        document.addEventListener('DOMContentLoaded', bindTopNav);
        document.addEventListener('livewire:navigated', bindTopNav);
        bindTopNav();
    })();
</script>
