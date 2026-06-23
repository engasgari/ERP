<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">مرکز گزارش‌ها</h2>
    </x-slot>

    <div id="top" class="py-8">
        <div class="mx-auto max-w-7xl px-3 sm:px-5 lg:px-6 space-y-6">
            <section class="rounded-3xl bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 p-6 text-white shadow-lg">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl">
                        <p class="text-xs font-bold uppercase tracking-[0.25em] text-emerald-300">Report Center</p>
                        <h1 class="mt-3 text-2xl font-black sm:text-3xl">همه گزارش‌های سیستم در یک مرکز دسته‌بندی‌شده</h1>
                        <p class="mt-3 text-sm leading-7 text-slate-300">
                            گزارش‌ها بر اساس بازرگانی، انبار، حقوق و دستمزد، کارکرد، پرسنل و مالی دسته‌بندی شده‌اند تا دسترسی سریع‌تر و استانداردتر باشد.
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                        @foreach ($groups as $group)
                            <a href="{{ route('management-reports.index', ['tab' => $group['key']]) }}" wire:navigate
                               class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-right transition hover:bg-white/10">
                                <div class="text-xs text-slate-300">تب گزارش</div>
                                <div class="mt-1 font-bold text-white">{{ $group['title'] }}</div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <div class="flex flex-wrap gap-2">
                    @foreach ($groups as $group)
                        <a href="{{ route('management-reports.index', ['tab' => $group['key']]) }}" wire:navigate
                           class="rounded-full px-4 py-2 text-sm font-bold transition {{ ($activeGroup['key'] ?? null) === $group['key'] ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                            {{ $group['title'] }}
                        </a>
                    @endforeach
                </div>
            </section>

            @php
                $visibleGroups = $activeGroup ? [$activeGroup] : $groups;
            @endphp

            <div class="space-y-6">
                @foreach ($visibleGroups as $group)
                    <section id="group-{{ $group['key'] }}" class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                        <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h3 class="text-xl font-black text-slate-900">{{ $group['title'] }}</h3>
                                <p class="mt-1 text-sm leading-7 text-slate-500">{{ $group['description'] }}</p>
                            </div>
                            <a href="#top" class="text-sm font-bold text-slate-600 transition hover:text-slate-900">بازگشت به بالا</a>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            @foreach ($group['reports'] as $report)
                                <a href="{{ $report['route'] }}" wire:navigate
                                   class="rounded-2xl border border-slate-200 bg-slate-50 p-4 transition hover:-translate-y-0.5 hover:bg-white hover:shadow-sm">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="text-base font-bold text-slate-900">{{ $report['title'] }}</div>
                                            <div class="mt-1 text-sm leading-6 text-slate-500">{{ $report['description'] }}</div>
                                        </div>
                                        <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">آماده</span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
