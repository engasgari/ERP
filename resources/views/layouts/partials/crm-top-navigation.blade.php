@php
    $brandRoute = $brandRoute ?? route('crm.dashboard');
    $brandMark = $brandMark ?? 'CRM';
    $brandTitle = $brandTitle ?? 'مدیریت CRM';
    $bottomNav = \App\Support\CrmNavigationMenu::mobileBottomBarForUser(auth()->user());
@endphp

<nav class="erp-top-nav crm-top-nav" aria-label="منوی CRM">
    <div class="erp-nav-inner crm-nav-inner">
        <a href="{{ $brandRoute }}" wire:navigate class="erp-brand crm-nav-brand">
            <span class="erp-brand-mark">{{ $brandMark }}</span>
            <span class="erp-brand-title crm-nav-brand-title">{{ $brandTitle }}</span>
        </a>

        <div class="crm-nav-mobile-tools">
            @if(Route::has('crm.search.index'))
                <a href="{{ route('crm.search.index') }}" wire:navigate class="crm-nav-icon-btn" aria-label="جستجو">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3-3"/></svg>
                </a>
            @endif
            <button type="button" class="crm-nav-icon-btn crm-nav-drawer-toggle" aria-label="باز کردن منو" aria-controls="crm-nav-drawer" aria-expanded="false" data-crm-nav-open>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
        </div>

        <div class="erp-nav-menu crm-nav-menu--desktop">
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

        <div class="erp-nav-user crm-nav-user--desktop">
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

<div id="crm-nav-drawer" class="crm-nav-drawer" hidden aria-hidden="true">
    <div class="crm-nav-drawer-backdrop" data-crm-nav-close tabindex="-1"></div>
    <aside class="crm-nav-drawer-panel" role="dialog" aria-modal="true" aria-label="منوی CRM" tabindex="-1">
        <div class="crm-nav-drawer-head">
            <div>
                <div class="crm-nav-drawer-kicker">مدیریت ارتباط با مشتری</div>
                <strong class="crm-nav-drawer-title">{{ $brandTitle }}</strong>
            </div>
            <button type="button" class="crm-nav-icon-btn" aria-label="بستن منو" data-crm-nav-close>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>

        <nav class="crm-nav-drawer-links" aria-label="صفحات CRM">
            @foreach ($navLinks as $link)
                @php
                    $patterns = (array) ($link['active'] ?? []);
                    $isActive = collect($patterns)->contains(fn ($pattern) => request()->routeIs($pattern));
                    $children = collect($link['children'] ?? []);
                    $parentUrl = Route::has($link['route'] ?? '') ? route($link['route'], $link['params'] ?? []) : '#';
                @endphp

                @if ($children->isNotEmpty())
                    <div class="crm-nav-drawer-group {{ $isActive ? 'is-active' : '' }}">
                        <div class="crm-nav-drawer-group-label">{{ $link['label'] }}</div>
                        @foreach ($children as $child)
                            @if (($child['type'] ?? null) !== 'divider')
                                <a href="{{ route($child['route'], $child['params'] ?? []) }}" wire:navigate class="crm-nav-drawer-link">{{ $child['label'] }}</a>
                            @endif
                        @endforeach
                    </div>
                @else
                    <a href="{{ $parentUrl }}" wire:navigate @class(['crm-nav-drawer-link', 'is-active' => $isActive])>{{ $link['label'] }}</a>
                @endif
            @endforeach
        </nav>

        @auth
            <div class="crm-nav-drawer-foot">
                <a href="{{ route('profile.edit') }}" wire:navigate class="crm-nav-drawer-user">{{ Auth::user()->name }}</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="crm-nav-drawer-logout">خروج از حساب</button>
                </form>
            </div>
        @endauth
    </aside>
</div>

@if(count($bottomNav) > 0)
    <nav class="crm-bottom-nav" aria-label="ناوبری سریع موبایل">
        @foreach ($bottomNav as $item)
            @php
                $isActive = collect((array) ($item['active'] ?? []))->contains(fn ($pattern) => request()->routeIs($pattern));
            @endphp
            <a href="{{ route($item['route'], $item['params'] ?? []) }}" wire:navigate @class(['crm-bottom-nav-item', 'is-active' => $isActive])>
                <span class="crm-bottom-nav-icon" aria-hidden="true">
                    @include('layouts.partials.crm-nav-icon', ['icon' => $item['icon'] ?? 'link'])
                </span>
                <span class="crm-bottom-nav-label">{{ $item['label'] }}</span>
            </a>
        @endforeach
        <button type="button" class="crm-bottom-nav-item" data-crm-nav-open aria-label="منوی کامل">
            <span class="crm-bottom-nav-icon" aria-hidden="true">
                @include('layouts.partials.crm-nav-icon', ['icon' => 'menu'])
            </span>
            <span class="crm-bottom-nav-label">منو</span>
        </button>
    </nav>
@endif

<script>
    (() => {
        const bindCrmNav = () => {
            const drawer = document.getElementById('crm-nav-drawer');
            if (!drawer || drawer.dataset.bound === '1') {
                return;
            }
            drawer.dataset.bound = '1';

            const panel = drawer.querySelector('.crm-nav-drawer-panel');
            const toggles = document.querySelectorAll('[data-crm-nav-open]');
            const closers = drawer.querySelectorAll('[data-crm-nav-close]');

            const setOpen = (open) => {
                drawer.hidden = !open;
                drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
                document.body.classList.toggle('crm-nav-open', open);
                toggles.forEach((btn) => btn.setAttribute('aria-expanded', open ? 'true' : 'false'));
                if (open) {
                    panel?.focus();
                }
            };

            toggles.forEach((btn) => btn.addEventListener('click', () => setOpen(true)));
            closers.forEach((el) => el.addEventListener('click', () => setOpen(false)));
            drawer.querySelectorAll('a[wire\\:navigate]').forEach((link) => {
                link.addEventListener('click', () => setOpen(false));
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !drawer.hidden) {
                    setOpen(false);
                }
            });

            const desktopNav = document.querySelector('.crm-top-nav');
            if (desktopNav && desktopNav.dataset.dropdownBound !== '1') {
                desktopNav.dataset.dropdownBound = '1';
                const dropdowns = Array.from(desktopNav.querySelectorAll('.crm-nav-menu--desktop .erp-nav-item'));
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
                    if (!desktopNav.contains(event.target)) {
                        dropdowns.forEach((item) => { item.open = false; });
                    }
                });
            }
        };

        document.addEventListener('DOMContentLoaded', bindCrmNav);
        document.addEventListener('livewire:navigated', bindCrmNav);
        bindCrmNav();
    })();
</script>
