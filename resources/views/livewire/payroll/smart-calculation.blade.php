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

<div dir="rtl" class="smart-payroll-page space-y-4">
    <style>
        .smart-payroll-page .smart-toolbar {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            flex-wrap: wrap;
            margin-bottom: 0.75rem;
            padding: 0.45rem 0.6rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.4rem;
        }
        .smart-payroll-page .smart-tool-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
            min-height: 2rem;
            padding: 0.3rem 0.65rem;
            border: 1px solid #cbd5e1;
            border-radius: 0.375rem;
            background: #fff;
            color: #334155;
            font-size: 0.78rem;
            font-weight: 600;
            line-height: 1;
            cursor: pointer;
        }
        .smart-payroll-page .smart-tool-btn:hover:not(:disabled) {
            background: #e2e8f0;
            border-color: #94a3b8;
            color: #0f172a;
        }
        .smart-payroll-page .smart-tool-btn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }
        .smart-payroll-page .smart-tool-btn--primary {
            color: #1d4ed8;
            border-color: #bfdbfe;
            background: #eff6ff;
        }
        .smart-payroll-page .smart-tool-btn--primary:hover:not(:disabled) {
            background: #dbeafe;
            border-color: #93c5fd;
            color: #1e3a8a;
        }
        .smart-payroll-page .smart-tool-btn--apply {
            color: #0f766e;
            border-color: #99f6e4;
            background: #fff;
        }
        .smart-payroll-page .smart-tool-btn--apply:hover:not(:disabled) {
            background: #f0fdfa;
            border-color: #5eead4;
            color: #115e59;
        }
        .smart-payroll-page .smart-toolbar-count {
            margin-inline-start: 0.15rem;
            font-size: 0.78rem;
            color: #64748b;
        }
        .smart-payroll-page .smart-check {
            width: 13px;
            height: 13px;
            margin: 0;
            border-radius: 0 !important;
            accent-color: #2563eb;
            vertical-align: middle;
        }
        .smart-payroll-page .smart-money {
            min-height: 2rem;
            padding: 0.3rem 0.5rem;
            font-size: 0.78rem;
        }
    </style>

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
            <input type="checkbox" wire:model.live="useCalendarAttendance" class="smart-check mt-0.5">
            <span>
                <span class="font-semibold">کارکرد کامل بر اساس تقویم</span>
                <span class="text-slate-500"> — تردد نادیده؛ همه روزهای کاری با ۸ ساعت</span>
            </span>
        </label>
    </div>

    <div class="rounded-lg bg-white px-4 py-4 shadow-md sm:px-5">
        <div class="smart-toolbar">
            <x-erp.ui.money-input
                wire:model.blur="defaultNet"
                placeholder="مبلغ دلخواه"
                class="smart-money w-44"
            />
            <button
                type="button"
                class="smart-tool-btn smart-tool-btn--apply"
                title="اعمال مبلغ روی انتخاب‌شده‌ها"
                wire:click="applyDefaultNet"
                @disabled($selectedCount === 0)
            >
                <x-erp.ui.action-icon name="confirm" class="h-4 w-4" />
                <span>اعمال مبلغ</span>
            </button>
            <button
                type="button"
                class="smart-tool-btn smart-tool-btn--primary"
                title="اجرای محاسبه هوشمند"
                wire:click="run"
                wire:confirm="محاسبه هوشمند برای افراد انتخاب‌شده اجرا شود؟"
                wire:loading.attr="disabled"
                @disabled($selectedCount === 0)
            >
                <x-erp.ui.action-icon name="calculate" class="h-4 w-4" />
                <span wire:loading.remove wire:target="run">محاسبه هوشمند</span>
                <span wire:loading wire:target="run">در حال محاسبه...</span>
            </button>
            @if($selectedCount > 0)
                <span class="smart-toolbar-count">{{ toPersianDigits($selectedCount) }} مورد</span>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead class="border-b border-slate-200 bg-slate-50 text-slate-600">
                    <tr>
                        <th class="w-10 px-2 py-2 text-center">
                            <input type="checkbox" class="smart-check" wire:model.live="headerSelectAll" title="انتخاب همه">
                        </th>
                        <th class="px-2 py-2 text-right font-semibold">کد</th>
                        <th class="px-2 py-2 text-right font-semibold">نام</th>
                        <th class="px-2 py-2 text-right font-semibold">خالص فعلی</th>
                        <th class="px-2 py-2 text-right font-semibold">خالص هدف</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $index => $row)
                        <tr wire:key="emp-{{ $row['employee_id'] }}" class="hover:bg-slate-50/70">
                            <td class="px-2 py-1.5 text-center">
                                <input
                                    type="checkbox"
                                    wire:model.live="rows.{{ $index }}.selected"
                                    class="smart-check"
                                >
                            </td>
                            <td class="whitespace-nowrap px-2 py-1.5 text-slate-600">{{ $row['code'] ? toPersianDigits($row['code']) : '—' }}</td>
                            <td class="px-2 py-1.5 font-medium text-slate-900">{{ $row['name'] }}</td>
                            <td class="whitespace-nowrap px-2 py-1.5 tabular-nums text-slate-600">
                                {{ $row['current_net'] !== null ? formatMoney($row['current_net']) : '—' }}
                            </td>
                            <td class="px-2 py-1.5">
                                <x-erp.ui.money-input
                                    wire:model.blur="rows.{{ $index }}.target_net"
                                    placeholder="مبلغ دلخواه"
                                    class="smart-money"
                                />
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
