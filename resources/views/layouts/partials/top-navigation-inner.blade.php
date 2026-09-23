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

        <button
            type="button"
            class="erp-nav-toggle"
            aria-label="باز کردن منو"
            aria-controls="erp-nav-panel"
            aria-expanded="false"
            data-erp-nav-toggle
        >
            <svg class="erp-nav-toggle-icon erp-nav-toggle-icon--open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" />
            </svg>
            <svg class="erp-nav-toggle-icon erp-nav-toggle-icon--close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
            </svg>
        </button>

        <div id="erp-nav-panel" class="erp-nav-panel">
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
    </div>
</nav>

<script>
    (() => {
        const bindTopNav = () => {
            const nav = document.querySelector('.erp-top-nav:not(.crm-top-nav)');
            if (!nav || nav.dataset.dropdownBound === '1') {
                return;
            }

            nav.dataset.dropdownBound = '1';

            const toggle = nav.querySelector('[data-erp-nav-toggle]');
            const dropdowns = Array.from(nav.querySelectorAll('.erp-nav-item'));

            const setMobileOpen = (open) => {
                nav.classList.toggle('is-mobile-open', open);
                document.body.classList.toggle('erp-nav-mobile-open', open);
                if (toggle) {
                    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                    toggle.setAttribute('aria-label', open ? 'بستن منو' : 'باز کردن منو');
                }
                if (!open) {
                    dropdowns.forEach((item) => {
                        item.open = false;
                    });
                }
            };

            toggle?.addEventListener('click', () => {
                setMobileOpen(!nav.classList.contains('is-mobile-open'));
            });

            nav.querySelectorAll('a[wire\\:navigate], a[href]').forEach((link) => {
                link.addEventListener('click', () => {
                    if (window.matchMedia('(max-width: 1024px)').matches) {
                        setMobileOpen(false);
                    }
                });
            });

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
                    if (nav.classList.contains('is-mobile-open')) {
                        setMobileOpen(false);
                    }
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    dropdowns.forEach((item) => {
                        item.open = false;
                    });
                    if (nav.classList.contains('is-mobile-open')) {
                        setMobileOpen(false);
                    }
                }
            });

            window.addEventListener('resize', () => {
                if (!window.matchMedia('(max-width: 1024px)').matches) {
                    setMobileOpen(false);
                }
            });
        };

        document.addEventListener('DOMContentLoaded', bindTopNav);
        document.addEventListener('livewire:navigated', () => {
            document.body.classList.remove('erp-nav-mobile-open');
            document.querySelectorAll('.erp-top-nav:not(.crm-top-nav)').forEach((nav) => {
                nav.dataset.dropdownBound = '';
                nav.classList.remove('is-mobile-open');
            });
            bindTopNav();
        });
        bindTopNav();
    })();
</script>
