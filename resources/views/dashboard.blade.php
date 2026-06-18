<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">داشبورد</h2>
    </x-slot>

    <div class="py-12">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="mb-5">
                <h2 class="text-xl font-bold">خلاصه دسترسی‌های شما</h2>
                <p class="mt-1 text-sm text-gray-500">
                    اطلاعات این صفحه بر اساس دسترسی‌های فعال حساب کاربری شما نمایش داده می‌شود.
                </p>
            </div>

            @if($cards->isNotEmpty())
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                    @foreach($cards as $card)
                        <div class="rounded-lg bg-gray-50 p-4 shadow-sm">
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <span class="text-xs font-bold text-gray-500">{{ $card['group'] }}</span>
                                <span class="h-2 w-2 rounded-full bg-slate-500"></span>
                            </div>
                            <div class="text-sm font-bold text-gray-700">{{ $card['title'] }}</div>
                            <div class="mt-2 text-2xl font-black text-gray-900">{{ $card['value'] }}</div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-4 text-yellow-800">
                    هنوز هیچ دسترسی عملیاتی برای شما تنظیم نشده است.
                </div>
            @endif

            <div class="mt-8" x-data="dashboardShortcutBoard(@js($shortcuts), @js($shortcutStorageKey))" x-init="init()">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-lg font-bold">دسترسی‌های سریع</h3>

                    @if($shortcuts->isNotEmpty())
                        <div class="flex flex-wrap gap-2">
                            <button type="button" @click="showPicker = !showPicker"
                                    class="rounded bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200">
                                افزودن شورتکات
                            </button>
                            <button type="button" @click="reset()"
                                    class="rounded bg-white px-3 py-2 text-xs font-bold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">
                                بازنشانی
                            </button>
                        </div>
                    @endif
                </div>

                @if($shortcuts->isNotEmpty())
                    <div x-show="showPicker" x-transition class="mb-4 rounded-lg bg-slate-50 p-3">
                        <div class="mb-2 text-xs font-bold text-slate-500">موزائیک‌های قابل افزودن</div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2">
                            <template x-for="shortcut in hiddenShortcuts" :key="shortcut.id">
                                <button type="button" @click="add(shortcut.id)"
                                        class="flex min-h-12 items-center gap-2 rounded-md bg-white px-2 py-2 text-right text-xs font-bold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-100">
                                    <span class="dashboard-shortcut-icon grid h-7 w-7 shrink-0 place-items-center rounded bg-slate-200 p-1.5 text-slate-500" x-html="shortcut.iconSvg || defaultIconSvg"></span>
                                    <span class="min-w-0 truncate" x-text="shortcut.label"></span>
                                </button>
                            </template>
                        </div>
                        <div x-show="hiddenShortcuts.length === 0" class="rounded bg-white px-3 py-2 text-xs text-slate-500">
                            همه میانبرهای مجاز شما روی داشبورد هستند.
                        </div>
                    </div>

                    <div class="dashboard-shortcut-grid">
                        <template x-for="shortcut in visibleShortcuts" :key="shortcut.id">
                            <a :href="shortcut.url"
                               draggable="true"
                               @dragstart="draggingId = shortcut.id"
                               @dragover.prevent
                               @drop.prevent="moveBefore(shortcut.id)"
                               class="group relative flex min-h-12 items-center gap-1.5 rounded-md bg-slate-200/70 px-2 py-2 text-slate-700 ring-1 ring-slate-300 transition hover:bg-slate-100 hover:shadow-sm sm:gap-2 sm:px-2.5"
                               style="min-width: 0;">
                                <button type="button"
                                        @click.prevent.stop="remove(shortcut.id)"
                                        class="absolute left-1.5 top-1.5 grid h-5 w-5 place-items-center rounded bg-white text-xs font-black text-slate-400 opacity-0 ring-1 ring-slate-200 transition hover:text-red-600 group-hover:opacity-100"
                                        title="حذف موزائیک">
                                    ×
                                </button>
                                <div class="dashboard-shortcut-icon grid h-6 w-6 shrink-0 place-items-center rounded bg-slate-300 p-1 text-slate-600 sm:h-7 sm:w-7 sm:p-1.5" x-html="shortcut.iconSvg || defaultIconSvg"></div>
                                <div class="min-w-0 truncate text-[10px] font-black leading-4 text-slate-800 sm:text-[11px]" x-text="shortcut.label"></div>
                            </a>
                        </template>
                    </div>

                    <div x-show="visibleShortcuts.length === 0" class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-4 text-sm text-gray-600">
                        موزائیکی روی داشبورد نیست. از «افزودن شورتکات» یکی را اضافه کنید.
                    </div>
                @else
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-4 text-sm text-gray-600">
                        میانبر فعالی برای حساب شما وجود ندارد.
                    </div>
                @endif
            </div>
        </div>
    </div>

    <style>
        .dashboard-shortcut-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.5rem;
        }

        @media (min-width: 640px) {
            .dashboard-shortcut-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (min-width: 768px) {
            .dashboard-shortcut-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        @media (min-width: 1024px) {
            .dashboard-shortcut-grid {
                grid-template-columns: repeat(6, minmax(0, 1fr));
            }
        }

        @media (min-width: 1280px) {
            .dashboard-shortcut-grid {
                grid-template-columns: repeat(8, minmax(0, 1fr));
            }
        }

        .dashboard-shortcut-icon svg {
            display: block;
            width: 100%;
            height: 100%;
        }
    </style>

    <script>
        function dashboardShortcutBoard(availableShortcuts, storageKey) {
            return {
                availableShortcuts,
                storageKey,
                selectedIds: [],
                draggingId: null,
                showPicker: false,
                defaultIconSvg: '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7 0l2-2a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-2 2a5 5 0 0 0 7 7l1-1"/></svg>',
                init() {
                    const saved = JSON.parse(localStorage.getItem(this.storageKey) || 'null');
                    const availableIds = this.availableShortcuts.map((shortcut) => shortcut.id);
                    this.selectedIds = Array.isArray(saved)
                        ? saved.filter((id) => availableIds.includes(id))
                        : availableIds.slice(0, Math.min(8, availableIds.length));
                    this.persist();
                },
                get visibleShortcuts() {
                    return this.selectedIds
                        .map((id) => this.availableShortcuts.find((shortcut) => shortcut.id === id))
                        .filter(Boolean);
                },
                get hiddenShortcuts() {
                    return this.availableShortcuts.filter((shortcut) => !this.selectedIds.includes(shortcut.id));
                },
                add(id) {
                    if (!this.selectedIds.includes(id)) {
                        this.selectedIds.push(id);
                        this.persist();
                    }
                },
                remove(id) {
                    this.selectedIds = this.selectedIds.filter((selectedId) => selectedId !== id);
                    this.persist();
                },
                moveBefore(targetId) {
                    if (!this.draggingId || this.draggingId === targetId) {
                        return;
                    }

                    const withoutDragged = this.selectedIds.filter((id) => id !== this.draggingId);
                    const targetIndex = withoutDragged.indexOf(targetId);
                    withoutDragged.splice(targetIndex, 0, this.draggingId);
                    this.selectedIds = withoutDragged;
                    this.draggingId = null;
                    this.persist();
                },
                reset() {
                    this.selectedIds = this.availableShortcuts
                        .map((shortcut) => shortcut.id)
                        .slice(0, Math.min(8, this.availableShortcuts.length));
                    this.persist();
                },
                persist() {
                    localStorage.setItem(this.storageKey, JSON.stringify(this.selectedIds));
                },
            };
        }
    </script>
</x-app-layout>
