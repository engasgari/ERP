<div dir="rtl" class="space-y-6">
    @include('livewire.partials.flash')

    <div class="rounded-lg bg-white p-4 shadow-md sm:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h3 class="text-lg font-bold text-slate-900">خلاصه کارکرد ماهانه</h3>
                <p class="mt-1 text-sm leading-7 text-slate-500">
                    این صفحه خروجی موتور جدید حضور و غیاب را نشان می‌دهد؛ همین اعداد مبنای محاسبه حقوق ماهانه هستند.
                </p>
                @if($employeesWithoutSummary > 0)
                    <p class="mt-2 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800">
                        {{ number_format($employeesWithoutSummary) }} پرسنل فعال هنوز تخصیص گروه کاری ندارند؛ بدون گروه کاری، برنامه شیفت و تقویم برایشان قابل محاسبه نیست.
                    </p>
                @endif
            </div>

            <form wire:submit.prevent="rebuild" class="grid grid-cols-1 gap-3 sm:grid-cols-4 lg:min-w-[640px]">
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">سال</span>
                    <select wire:model.live="year" class="mt-1 w-full rounded-md border-gray-300 text-right">
                        @for($y = verta()->year; $y >= 1400; $y--)
                            <option value="{{ $y }}">{{ toPersianDigits($y) }}</option>
                        @endfor
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">ماه</span>
                    <select wire:model.live="month" class="mt-1 w-full rounded-md border-gray-300 text-right">
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}">{{ getPersianMonthName($m) }}</option>
                        @endfor
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">جستجو</span>
                    <input wire:model.live.debounce.400ms="search" placeholder="پرسنل" class="mt-1 w-full rounded-md border-gray-300 text-right">
                </label>

                <div class="flex items-end">
                    <button type="submit" wire:loading.attr="disabled" wire:target="rebuild" class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-60">
                        بازسازی کارکرد
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="rounded-lg bg-white p-4 shadow-md sm:p-6">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ getPersianMonthName($month) }} {{ toPersianDigits($year) }}</h3>
            <div wire:loading.delay class="text-sm text-slate-500">در حال به‌روزرسانی...</div>
        </div>

        <div class="overflow-x-auto">
            <table class="erp-ui-data-table min-w-full">
                <thead>
                <tr>
                    <th>پرسنل</th>
                    <th>برنامه</th>
                    <th>کارکرد</th>
                    <th>اضافه‌کاری</th>
                    <th>مرخصی</th>
                    <th>ماموریت</th>
                    <th>تاخیر/تعجیل</th>
                    <th>غیبت</th>
                </tr>
                </thead>
                <tbody>
                @forelse($summaries as $summary)
                    <tr wire:key="summary-{{ $summary->id }}">
                        <td class="font-semibold text-slate-900">{{ $summary->employee?->full_name }}</td>
                        <td>{{ number_format($summary->planned_minutes / 60, 2) }} ساعت</td>
                        <td>{{ number_format($summary->worked_minutes / 60, 2) }} ساعت</td>
                        <td>{{ number_format($summary->overtime_minutes / 60, 2) }} ساعت</td>
                        <td>
                            {{ number_format($summary->hourly_leave_minutes / 60, 2) }} ساعت
                            /
                            {{ number_format((float) $summary->daily_leave_days, 2) }} روز
                        </td>
                        <td>
                            {{ number_format($summary->hourly_mission_minutes / 60, 2) }} ساعت
                            /
                            {{ number_format((float) $summary->daily_mission_days, 2) }} روز
                        </td>
                        <td>{{ number_format(($summary->delay_minutes + $summary->early_leave_minutes) / 60, 2) }} ساعت</td>
                        <td>{{ number_format($summary->absence_minutes / 60, 2) }} ساعت</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="py-8 text-center text-slate-500">برای این دوره هنوز خلاصه کارکردی ثبت نشده است. اگر دوره حقوق ساخته شده، دکمه بازسازی کارکرد را بزنید.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $summaries->links() }}</div>
    </div>
</div>

