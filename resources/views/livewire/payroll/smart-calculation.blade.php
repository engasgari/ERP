@php
    $statusLabels = [
        'draft' => 'پیش‌نویس',
        'calculated' => 'محاسبه‌شده',
        'approved' => 'تاییدشده',
        'closed' => 'بسته‌شده',
    ];
    $statusBadge = [
        'draft' => 'bg-slate-100 text-slate-700',
        'calculated' => 'bg-sky-50 text-sky-700',
        'approved' => 'bg-emerald-50 text-emerald-700',
        'closed' => 'bg-amber-50 text-amber-800',
    ];
@endphp

<div dir="rtl" class="space-y-4">
    @include('livewire.partials.flash')

    <div class="rounded-lg bg-white px-4 py-5 shadow-md sm:px-5 sm:py-6">
        <div class="flex flex-col gap-2.5 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <h2 class="text-base font-bold text-slate-900">محاسبه هوشمند حقوق</h2>
                    @if($period)
                        <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-700">
                            {{ $period->persian_title ?? $period->title }}
                        </span>
                        <span @class([
                            'rounded px-2 py-0.5 text-[10px] font-semibold',
                            $statusBadge[$period->status] ?? 'bg-slate-100 text-slate-700',
                        ])>
                            {{ $statusLabels[$period->status] ?? $period->status }}
                        </span>
                        <span class="text-[10px] text-slate-400">
                            روز کاری تقویم: {{ toPersianDigits($workdayCount ?? 0) }}
                        </span>
                    @else
                        <span class="rounded bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700">دوره ساخته نشده</span>
                        <span class="text-[10px] text-slate-400">
                            روز کاری تقویم: {{ toPersianDigits($workdayCount ?? 0) }}
                        </span>
                    @endif
                </div>
                <p class="mt-0.5 text-[11px] leading-4 text-slate-500">
                    کارکرد تقویمی کامل (بدون تردد) → محاسبه حقوق با خالص هدف
                </p>
            </div>

            <div class="flex flex-wrap items-end gap-2">
                <label class="block">
                    <span class="text-[10px] font-medium text-slate-600">سال</span>
                    <select wire:model.live="year" class="mt-0.5 rounded-md border-gray-300 py-1.5 text-xs text-right">
                        @foreach($years as $y)
                            <option value="{{ $y }}">{{ toPersianDigits($y) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="text-[10px] font-medium text-slate-600">ماه</span>
                    <select wire:model.live="month" class="mt-0.5 rounded-md border-gray-300 py-1.5 text-xs text-right">
                        @foreach($months as $m)
                            <option value="{{ $m }}">{{ getPersianMonthName($m) }}</option>
                        @endforeach
                    </select>
                </label>
                <a href="{{ route('payroll.periods.index') }}" class="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    دوره‌های حقوق
                </a>
                <a href="{{ route('insurance.payments.index') }}" class="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    پرداخت بیمه
                </a>
            </div>
        </div>

        <label class="mt-3 flex items-start gap-2 rounded-md border border-slate-200 bg-slate-50 px-2.5 py-2 text-[11px] text-slate-700">
            <input type="checkbox" wire:model.live="useCalendarAttendance" class="mt-0.5 h-3.5 w-3.5 rounded-none border-gray-300 text-sky-600 focus:ring-sky-500">
            <span>
                <span class="font-semibold">کارکرد کامل بر اساس تقویم</span>
                <span class="text-slate-500"> — تردد نادیده؛ همه روزهای کاری با ۸ ساعت</span>
            </span>
        </label>
    </div>

    <div class="rounded-lg bg-white px-4 py-4 shadow-md sm:px-5">
        <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-1.5">
                <button
                    type="button"
                    wire:click="selectAll(true)"
                    title="انتخاب همه"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 text-emerald-700 hover:bg-emerald-50"
                >
                    <x-erp.ui.action-icon name="confirm" class="h-4 w-4" />
                    <span class="sr-only">انتخاب همه</span>
                </button>
                <button
                    type="button"
                    wire:click="selectAll(false)"
                    title="حذف انتخاب"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 text-rose-700 hover:bg-rose-50"
                >
                    <x-erp.ui.action-icon name="reject" class="h-4 w-4" />
                    <span class="sr-only">حذف انتخاب</span>
                </button>
            </div>

            <div class="flex flex-wrap items-end gap-2">
                <label class="block">
                    <span class="text-[10px] font-medium text-slate-600">خالص یکسان (ریال)</span>
                    <input type="text" inputmode="numeric" wire:model="defaultNet" placeholder="250000000" class="mt-0.5 w-40 rounded-md border-gray-300 py-1.5 text-xs text-right" />
                </label>
                <button type="button" wire:click="applyDefaultNet" class="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    اعمال
                </button>
                <button
                    type="button"
                    wire:click="run"
                    wire:confirm="محاسبه هوشمند برای افراد انتخاب‌شده اجرا شود؟"
                    wire:loading.attr="disabled"
                    class="rounded-md bg-slate-900 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-slate-700 disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="run">اجرای محاسبه هوشمند</span>
                    <span wire:loading wire:target="run">در حال محاسبه...</span>
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead class="border-b border-slate-200 bg-slate-50 text-slate-600">
                    <tr>
                        <th class="w-10 px-2 py-2 text-center font-semibold"> </th>
                        <th class="px-2 py-2 text-right font-semibold">کد</th>
                        <th class="px-2 py-2 text-right font-semibold">نام</th>
                        <th class="px-2 py-2 text-right font-semibold">خالص فعلی</th>
                        <th class="px-2 py-2 text-right font-semibold">خالص هدف (ریال)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $index => $row)
                        <tr wire:key="emp-{{ $row['employee_id'] }}" class="hover:bg-slate-50/70">
                            <td class="px-2 py-1.5 text-center">
                                <input
                                    type="checkbox"
                                    wire:model.live="rows.{{ $index }}.selected"
                                    class="h-3.5 w-3.5 rounded-none border-gray-300 text-sky-600 focus:ring-sky-500"
                                >
                            </td>
                            <td class="whitespace-nowrap px-2 py-1.5 text-slate-600">{{ $row['code'] ? toPersianDigits($row['code']) : '—' }}</td>
                            <td class="px-2 py-1.5 font-medium text-slate-900">{{ $row['name'] }}</td>
                            <td class="whitespace-nowrap px-2 py-1.5 text-slate-600">
                                {{ $row['current_net'] !== null ? formatMoney($row['current_net']) : '—' }}
                            </td>
                            <td class="px-2 py-1.5">
                                <input
                                    type="text"
                                    inputmode="numeric"
                                    wire:model.defer="rows.{{ $index }}.target_net"
                                    class="w-full min-w-[8rem] rounded-md border-gray-300 py-1 text-xs text-right"
                                    placeholder="250000000"
                                >
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-2 py-8 text-center text-slate-500">کارمند فعالی یافت نشد.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
