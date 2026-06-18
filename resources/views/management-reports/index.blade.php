<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">گزارش‌ها</h2>
    </x-slot>

    <div class="py-12">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="mb-5">
                <h2 class="text-xl font-bold">مرکز گزارش‌ها</h2>
                <p class="mt-1 text-sm text-gray-500">
                    گزارش‌ها به صورت دسته‌بندی شده آماده می‌شوند؛ هر بخش را کم‌کم کامل می‌کنیم.
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
                @foreach($groups as $group)
                    <section class="rounded-lg bg-gray-50 p-4 shadow-sm">
                        <div class="mb-3">
                            <h3 class="font-bold text-gray-800">{{ $group['title'] }}</h3>
                            <p class="mt-1 text-xs leading-5 text-gray-500">{{ $group['description'] }}</p>
                        </div>

                        <div class="space-y-2">
                            @forelse($group['reports'] as $report)
                                <a href="{{ $report['route'] }}" wire:navigate
                                   class="block rounded-md bg-white px-3 py-2 text-sm shadow-sm transition hover:bg-gray-100">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-semibold text-gray-800">{{ $report['title'] }}</span>
                                        <span class="rounded bg-green-50 px-2 py-0.5 text-xs text-green-700">{{ $report['status'] }}</span>
                                    </div>
                                    <div class="mt-1 text-xs leading-5 text-gray-500">{{ $report['description'] }}</div>
                                </a>
                            @empty
                                <div class="rounded-md bg-white px-3 py-3 text-sm text-gray-500 shadow-sm">
                                    هنوز گزارشی برای این بخش ساخته نشده است.
                                </div>
                            @endforelse
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
