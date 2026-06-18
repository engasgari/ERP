<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">مرکز گزارش‌های مالی</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-3 sm:px-5 lg:px-6 space-y-6">
            @foreach($groups as $group)
                <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <div class="mb-4">
                        <h3 class="text-xl font-black text-slate-900">{{ $group['title'] }}</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ $group['description'] }}</p>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        @foreach($group['reports'] as $report)
                            <a href="{{ route('financial-reports.show', ['report' => $report['key']]) }}" wire:navigate class="rounded-2xl border border-slate-200 bg-slate-50 p-4 transition hover:bg-white hover:shadow-sm">
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
</x-app-layout>
