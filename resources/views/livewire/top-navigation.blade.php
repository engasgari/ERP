<nav class="erp-top-nav sticky top-0 z-40 border-b border-slate-200 bg-white/95 shadow-sm backdrop-blur">
    <div class="mx-auto flex h-14 max-w-7xl items-center gap-3 px-3 sm:px-5 lg:px-6">
        <a href="{{ route('projects.index') }}" class="flex shrink-0 items-center gap-2 rounded-md px-2 py-1.5 text-sm font-bold text-slate-900 hover:bg-slate-100">
            <span class="grid h-8 w-8 place-items-center rounded-md bg-teal-600 text-xs font-black text-white">ERP</span>
            <span class="hidden sm:inline">مدیریت ERP</span>
        </a>

        <div class="hidden flex-1 justify-center lg:flex">
            <div class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-slate-50 p-1">
                @foreach ($links as $link)
                    <a href="{{ route($link['route']) }}"
                       wire:navigate
                       class="rounded px-3 py-1.5 text-xs font-semibold transition {{ request()->routeIs($link['active']) ? 'bg-white text-teal-700 shadow-sm ring-1 ring-slate-200' : 'text-slate-600 hover:bg-white hover:text-slate-900' }}">
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </div>
        </div>

        <div class="ms-auto hidden items-center gap-2 lg:flex">
            <a href="{{ route('profile.edit') }}" wire:navigate class="rounded-md px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                {{ Auth::user()->name }}
            </a>
            <button type="button" wire:click="logout" class="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700">
                خروج
            </button>
        </div>

        <button type="button" wire:click="$toggle('open')" class="ms-auto inline-flex h-9 w-9 items-center justify-center rounded-md border border-slate-200 text-slate-700 hover:bg-slate-100 lg:hidden" aria-label="باز کردن منو">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" />
            </svg>
        </button>
    </div>

    @if ($open)
        <div class="border-t border-slate-200 bg-white px-3 py-3 lg:hidden">
            <div class="grid gap-1">
                @foreach ($links as $link)
                    <a href="{{ route($link['route']) }}"
                       wire:navigate
                       class="rounded-md px-3 py-2 text-sm font-semibold {{ request()->routeIs($link['active']) ? 'bg-teal-50 text-teal-700' : 'text-slate-700 hover:bg-slate-100' }}">
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </div>

            <div class="mt-3 flex items-center justify-between border-t border-slate-200 pt-3">
                <a href="{{ route('profile.edit') }}" wire:navigate class="text-sm font-medium text-slate-700">{{ Auth::user()->name }}</a>
                <button type="button" wire:click="logout" class="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">
                    خروج
                </button>
            </div>
        </div>
    @endif
</nav>
