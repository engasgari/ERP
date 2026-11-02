@php
    $authUser = auth()->user();
    $canCreatePeriod = (bool) $authUser?->hasPermission('payroll.periods.create');
    $canCalculatePayroll = (bool) $authUser?->hasPermission('payroll.calculate');
    $canApprovePayroll = (bool) $authUser?->hasPermission('payroll.approve');
    $canDeletePeriod = (bool) $authUser?->hasPermission('payroll.periods.delete');
    $canClosePeriod = (bool) $authUser?->hasPermission('payroll.periods.close');
    $canPay = (bool) $authUser?->hasPermission('payroll.adjust');
    $canAdjust = $canPay;
    $statusLabels = [
        'draft' => 'پیش‌نویس',
        'calculated' => 'محاسبه‌شده',
        'approved' => 'تاییدشده',
        'closed' => 'بسته‌شده',
    ];
@endphp

<div dir="rtl" class="space-y-6">
    @include('livewire.partials.flash')

    <div class="rounded-lg bg-white px-4 py-5 shadow-md sm:px-5 sm:py-6">
        <div class="flex flex-col gap-2.5 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <h2 class="text-base font-bold text-slate-900">حقوق و دستمزد</h2>
                    @if($period)
                        <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-700">
                            {{ $period->persian_title }} — {{ $statusLabels[$period->status] ?? $period->status }}
                        </span>
                        <span class="text-[10px] text-slate-400">
                            {{ formatJalaliDateSafe($period->starts_at) }} تا {{ formatJalaliDateSafe($period->ends_at) }}
                        </span>
                    @else
                        <span class="rounded bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700">دوره ساخته نشده</span>
                    @endif
                </div>
                <p class="mt-0.5 text-[11px] leading-4 text-slate-500">
                    بررسی کارکرد → محاسبه حقوق → پرداخت
                </p>
            </div>

            <div class="flex flex-wrap items-end gap-2">
                <label class="block">
                    <span class="text-[10px] font-medium text-slate-600">سال</span>
                    <select wire:model.live="year" class="mt-0.5 rounded-md border-gray-300 py-1.5 text-xs text-right">
                        @for($y = verta()->year; $y >= 1400; $y--)
                            <option value="{{ $y }}">{{ toPersianDigits($y) }}</option>
                        @endfor
                    </select>
                </label>
                <label class="block">
                    <span class="text-[10px] font-medium text-slate-600">ماه</span>
                    <select wire:model.live="month" class="mt-0.5 rounded-md border-gray-300 py-1.5 text-xs text-right">
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}">{{ getPersianMonthName($m) }}</option>
                        @endfor
                    </select>
                </label>
                @if(! $period && $canCreatePeriod)
                    <button type="button" wire:click="ensurePeriod" class="rounded-md bg-slate-900 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-slate-700">
                        آماده‌سازی دوره
                    </button>
                @endif
                @if($canDeletePeriod && $period && in_array($period->status, ['draft', 'calculated'], true))
                    <button type="button" wire:click="deletePeriod" wire:confirm="این دوره و محاسباتش حذف شود؟" class="rounded-md border border-red-200 px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">
                        حذف دوره
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Stepper --}}
    @php
        $stepCount = max(1, count($steps));
    @endphp
    <nav aria-label="مراحل حقوق و دستمزد" class="px-1 py-2">
        <style>
            /* Single-layer arrow border: outer = border color, inner = face (2px ring) */
            .payroll-step-arrow-shell {
                clip-path: polygon(0 50%, 24px 0, 100% 0, 100% 100%, 24px 100%);
            }
            .payroll-step-arrow-face {
                clip-path: polygon(2px 50%, 25px 2px, calc(100% - 2px) 2px, calc(100% - 2px) calc(100% - 2px), 25px calc(100% - 2px));
            }
            @media (max-width: 639px) {
                .payroll-step-arrow-shell {
                    clip-path: polygon(0 0, 100% 0, 100% calc(100% - 18px), 50% 100%, 0 calc(100% - 18px));
                }
                .payroll-step-arrow-face {
                    clip-path: polygon(2px 2px, calc(100% - 2px) 2px, calc(100% - 2px) calc(100% - 19px), 50% calc(100% - 2px), 2px calc(100% - 19px));
                }
            }
        </style>
        <ol class="mx-auto flex max-w-4xl flex-col gap-2 sm:flex-row sm:items-stretch sm:gap-1">
            @foreach($steps as $index => $item)
                @php $isArrowCard = $index < $stepCount - 1; @endphp
                <li class="flex min-w-0 flex-1 items-stretch sm:justify-center">
                    <div
                        @class([
                            'relative w-full max-w-xs transition sm:max-w-none',
                            'payroll-step-arrow-shell' => $isArrowCard,
                            'rounded-2xl border-2 border-slate-200' => ! $isArrowCard,
                            'bg-slate-200' => $isArrowCard,
                            'opacity-100' => $item['current'],
                            'opacity-70' => ! $item['current'] && ! $item['locked'],
                            'opacity-40' => $item['locked'],
                        ])
                    >
                        <button
                            type="button"
                            wire:click="goToStep({{ $item['number'] }})"
                            @disabled($item['locked'])
                            @class([
                                'group flex h-full w-full items-center gap-3 px-3 py-2.5 text-right transition sm:flex-col sm:items-center sm:gap-2.5 sm:px-4 sm:py-3 sm:text-center',
                                'payroll-step-arrow-face ps-8' => $isArrowCard,
                                'rounded-[14px]' => ! $isArrowCard,
                                'bg-sky-100' => $item['current'],
                                'bg-white hover:bg-slate-50' => ! $item['current'] && ! $item['locked'],
                                'bg-white cursor-not-allowed' => $item['locked'],
                            ])
                        >
                            <span @class([
                                'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-base font-black',
                                'h-11 w-11 border-[3px] border-slate-200 bg-sky-200 text-sky-800' => $item['current'],
                                'bg-emerald-100 text-emerald-700' => $item['done'] && ! $item['current'],
                                'bg-slate-100 text-slate-500' => ! $item['done'] && ! $item['current'],
                            ])>
                                {{ toPersianDigits($item['number']) }}
                            </span>

                            <span class="flex min-w-0 flex-1 items-center gap-2 sm:flex-none sm:justify-center">
                                <span class="min-w-0 flex-1 text-right sm:flex-none sm:text-center">
                                    <span @class([
                                        'inline-flex items-center gap-1.5 text-[11px] font-semibold',
                                        'text-sky-700' => $item['current'],
                                        'text-emerald-600' => $item['done'] && ! $item['current'],
                                        'text-slate-400' => ! $item['done'] && ! $item['current'],
                                    ])>
                                        مرحله {{ toPersianDigits($item['number']) }}
                                    </span>
                                    <span @class([
                                        'mt-1 block text-sm font-bold leading-5',
                                        'text-sky-900' => $item['current'],
                                        'text-slate-600' => ! $item['current'],
                                    ])>{{ $item['title'] }}</span>
                                    <span @class([
                                        'mt-0.5 block text-[11px] leading-4 sm:mx-auto sm:max-w-[11rem]',
                                        'text-sky-700/70' => $item['current'],
                                        'text-slate-400' => ! $item['current'],
                                    ])>{{ $item['description'] }}</span>
                                </span>

                                @if($item['current'])
                                    <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white" title="مرحله فعلی">
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                @endif
                            </span>
                        </button>
                    </div>
                </li>
            @endforeach
        </ol>
    </nav>

    {{-- Step 1: Attendance (display only) --}}
    @if($step === 1)
        <div class="rounded-lg bg-white p-3 shadow-md sm:p-4 space-y-3">
            <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-base font-bold text-slate-900">مرحله ۱ — کارکرد ماهانه</h3>
                    <p class="mt-0.5 text-xs text-slate-500">خلاصه کارکرد ماه را ببینید و تایید کنید. محاسبه و ویرایش از صفحه حضور و غیاب است.</p>
                </div>
                <a href="{{ route('attendance.calculations', ['year' => $year, 'month' => $month]) }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    جزئیات/ویرایش کارکرد
                </a>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <div class="text-xs text-slate-500">تعداد نفرات</div>
                    <div class="mt-1 text-lg font-bold tabular-nums text-slate-900">{{ formatMoney($summary['attendance_count']) }}</div>
                </div>
                <div class="rounded-lg border border-emerald-100 bg-emerald-50 p-3">
                    <div class="text-xs text-emerald-700">جمع ساعت عادی</div>
                    <div class="mt-1 text-lg font-bold tabular-nums text-emerald-800">{{ formatMoney((float) $attendances->sum('normal_hours'), 2) }}</div>
                </div>
                <div class="rounded-lg border border-blue-100 bg-blue-50 p-3">
                    <div class="text-xs text-blue-700">جمع اضافه‌کاری</div>
                    <div class="mt-1 text-lg font-bold tabular-nums text-blue-800">{{ formatMoney((float) $attendances->sum('overtime_hours'), 2) }}</div>
                </div>
                <div class="rounded-lg border border-amber-100 bg-amber-50 p-3">
                    <div class="text-xs text-amber-700">جمع غیبت (ساعت)</div>
                    <div class="mt-1 text-lg font-bold tabular-nums text-amber-800">{{ formatMoney((float) $attendances->sum('absence_hours'), 2) }}</div>
                </div>
            </div>

            <div class="overflow-hidden rounded-md border border-slate-200">
                <div class="divide-y divide-slate-100">
                    @forelse($attendances as $row)
                        @php
                            $rowStatus = (string) ($row->status ?: 'ثبت‌شده');
                            $statusClass = match (true) {
                                str_contains($rowStatus, 'ناموفق') || str_contains($rowStatus, 'خطا') => 'bg-red-100 text-red-700',
                                str_contains($rowStatus, 'تایید') || str_contains($rowStatus, 'نهایی') => 'bg-blue-100 text-blue-700',
                                default => 'bg-emerald-100 text-emerald-700',
                            };
                        @endphp
                        <div wire:key="att-{{ $row->id }}" class="flex items-center gap-3 bg-white px-3 py-2 hover:bg-slate-50/80">
                            <span class="inline-flex w-[4.75rem] shrink-0 justify-center rounded px-1.5 py-0.5 text-[10px] font-semibold leading-4 {{ $statusClass }}">
                                {{ $rowStatus }}
                            </span>

                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold leading-5 text-slate-900">{{ $row->employee?->full_name }}</div>
                                <div class="mt-1 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-xs text-slate-600">
                                    <span>
                                        <span class="text-[10px] text-slate-400">عادی</span>
                                        <span class="ms-1 font-semibold tabular-nums text-slate-800">{{ formatMoney((float) $row->normal_hours, 2) }}</span>
                                    </span>
                                    <span class="select-none text-slate-300" aria-hidden="true">┊</span>
                                    <span>
                                        <span class="text-[10px] text-slate-400">اضافه</span>
                                        <span class="ms-1 font-semibold tabular-nums text-slate-800">{{ formatMoney((float) $row->overtime_hours, 2) }}</span>
                                    </span>
                                    <span class="select-none text-slate-300" aria-hidden="true">┊</span>
                                    <span>
                                        <span class="text-[10px] text-slate-400">ماموریت</span>
                                        <span class="ms-1 font-semibold tabular-nums text-slate-800">{{ formatMoney((float) $row->mission_hours, 2) }}</span>
                                    </span>
                                    <span class="select-none text-slate-300" aria-hidden="true">┊</span>
                                    <span>
                                        <span class="text-[10px] text-slate-400">مرخصی</span>
                                        <span class="ms-1 font-semibold tabular-nums text-slate-800">{{ formatMoney((float) $row->leave_hours, 2) }}</span>
                                    </span>
                                    <span class="select-none text-slate-300" aria-hidden="true">┊</span>
                                    <span>
                                        <span class="text-[10px] text-amber-600">غیبت</span>
                                        <span class="ms-1 font-bold tabular-nums text-amber-800">{{ formatMoney((float) $row->absence_hours, 2) }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="px-3 py-6 text-center text-xs text-slate-500">برای این ماه کارکردی ثبت نشده است. از صفحه محاسبه کارکرد اقدام کنید.</div>
                    @endforelse
                </div>
            </div>

            <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-3">
                <button
                    type="button"
                    wire:click="confirmAttendanceAndNext"
                    wire:confirm="کارکرد ماهانه تایید شود و به محاسبه حقوق برویم؟"
                    @disabled($summary['attendance_count'] < 1)
                    class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                >
                    تایید کارکرد و مرحله بعد
                </button>
            </div>
        </div>
    @endif

    {{-- Step 2: Payroll --}}
    @if($step === 2)
        <div class="rounded-lg bg-white p-3 shadow-md sm:p-4 space-y-3">
            <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-base font-bold text-slate-900">مرحله ۲ — محاسبه حقوق</h3>
                    <p class="mt-0.5 text-xs text-slate-500">حقوق را محاسبه کنید، مبالغ را در صورت نیاز اصلاح کنید، سپس برای پرداخت تایید کنید.</p>
                </div>
                @if($canCalculatePayroll && $period && $period->status !== 'closed')
                    <button type="button" wire:click="calculatePayroll" wire:loading.attr="disabled" wire:target="calculatePayroll" wire:confirm="محاسبه حقوق این دوره انجام/به‌روز شود؟" class="rounded-md bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-700 disabled:opacity-60">
                        <span wire:loading.remove wire:target="calculatePayroll">محاسبه حقوق</span>
                        <span wire:loading wire:target="calculatePayroll">در حال محاسبه…</span>
                    </button>
                @endif
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <div class="text-xs text-slate-500">تعداد</div>
                    <div class="mt-1 text-lg font-bold tabular-nums text-slate-900">{{ formatMoney($summary['calculation_count']) }}</div>
                </div>
                <div class="rounded-lg border border-emerald-100 bg-emerald-50 p-3">
                    <div class="text-xs text-emerald-700">موفق</div>
                    <div class="mt-1 text-lg font-bold tabular-nums text-emerald-800">{{ formatMoney($summary['success_count']) }}</div>
                </div>
                <div class="rounded-lg border border-red-100 bg-red-50 p-3">
                    <div class="text-xs text-red-700">ناموفق</div>
                    <div class="mt-1 text-lg font-bold tabular-nums text-red-800">{{ formatMoney($summary['failed_count']) }}</div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <div class="text-xs text-slate-500">ناخالص</div>
                    <div class="mt-1 text-lg font-bold tabular-nums text-slate-900">{{ formatMoney($summary['gross_total']) }}</div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <div class="text-xs text-slate-500">کسورات</div>
                    <div class="mt-1 text-lg font-bold tabular-nums text-slate-900">{{ formatMoney($summary['deduction_total']) }}</div>
                </div>
                <div class="rounded-lg border border-emerald-100 bg-emerald-50 p-3">
                    <div class="text-xs text-emerald-700">خالص</div>
                    <div class="mt-1 text-lg font-bold tabular-nums text-emerald-800">{{ formatMoney($summary['net_total']) }}</div>
                </div>
            </div>

            <div class="overflow-hidden rounded-md border border-slate-200">
                <div class="divide-y divide-slate-100">
                    @forelse($calculations as $calculation)
                        @php
                            $isAdjusted = (bool) data_get($calculation->payslip?->snapshot, 'manually_adjusted', false);
                            $paidAmount = (float) ($calculation->payments_sum_amount ?? $calculation->payments->sum('amount'));
                            $isPaid = $paidAmount > 0 && $paidAmount >= (float) $calculation->net_payable && (float) $calculation->net_payable > 0;
                            $statusLabel = $calculation->status === 'failed' ? 'ناموفق' : ($isPaid ? 'پرداخت‌شده' : 'محاسبه‌شده');
                            $statusClass = $calculation->status === 'failed'
                                ? 'bg-red-100 text-red-700'
                                : ($isPaid ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700');
                            $canEditAmounts = $canAdjust
                                && $calculation->status !== 'failed'
                                && $calculation->payments->isEmpty()
                                && $period
                                && $period->status !== 'closed';
                        @endphp
                        <div wire:key="calc-row-{{ $calculation->id }}" class="flex items-center gap-3 bg-white px-3 py-2 hover:bg-slate-50/80">
                            <span class="inline-flex w-[4.75rem] shrink-0 justify-center rounded px-1.5 py-0.5 text-[10px] font-semibold leading-4 {{ $statusClass }}">
                                {{ $statusLabel }}
                            </span>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                    <span class="text-sm font-semibold leading-5 text-slate-900">{{ $calculation->employee?->full_name }}</span>
                                    @if($isAdjusted)
                                        <span class="rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-700">اصلاح دستی</span>
                                    @endif
                                    <span class="text-[11px] text-slate-400">
                                        {{ formatMoney((float) $calculation->attendance?->normal_hours, 2) }}ع
                                        ·
                                        {{ formatMoney((float) $calculation->attendance?->overtime_hours, 2) }}اضافه
                                    </span>
                                </div>
                                <div class="mt-1 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-xs text-slate-600">
                                    <span>
                                        <span class="text-[10px] text-slate-400">ناخالص</span>
                                        <span class="ms-1 font-semibold tabular-nums text-slate-800">{{ formatMoney((float) $calculation->gross_salary) }}</span>
                                    </span>
                                    <span class="select-none text-slate-300" aria-hidden="true">┊</span>
                                    <span>
                                        <span class="text-[10px] text-slate-400">کسورات</span>
                                        <span class="ms-1 font-semibold tabular-nums text-slate-800">{{ formatMoney((float) $calculation->total_deductions) }}</span>
                                    </span>
                                    <span class="select-none text-slate-300" aria-hidden="true">┊</span>
                                    <span>
                                        <span class="text-[10px] text-emerald-600">خالص</span>
                                        <span class="ms-1 font-bold tabular-nums text-emerald-800">{{ formatMoney((float) $calculation->net_payable) }}</span>
                                    </span>
                                </div>
                                @if($calculation->failure_reason)
                                    <div class="mt-1 text-[10px] leading-4 text-red-600">{{ $calculation->failure_reason }}</div>
                                @endif
                            </div>

                            <div class="flex shrink-0 items-center gap-0.5">
                                <x-erp.ui.row-actions class="!gap-0.5">
                                    @if($canEditAmounts)
                                        <x-erp.ui.row-action icon="edit" label="ویرایش مبالغ" wire:click="startEdit({{ $calculation->id }})" />
                                    @endif
                                    @if($calculation->payslip)
                                        <x-erp.ui.row-action icon="print" label="چاپ فیش" :href="route('payslips.print', $calculation->payslip)" target="_blank" />
                                    @endif
                                </x-erp.ui.row-actions>
                            </div>
                        </div>
                    @empty
                        <div class="px-3 py-6 text-center text-xs text-slate-500">هنوز محاسبه حقوقی انجام نشده است.</div>
                    @endforelse
                </div>
            </div>

            <div class="flex flex-wrap justify-between gap-2 border-t border-slate-100 pt-3">
                <button type="button" wire:click="goToStep(1)" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    بازگشت به کارکرد
                </button>
                <div class="flex flex-wrap gap-2">
                    @if($canApprovePayroll)
                        @php
                            $canConfirmPayroll = $period
                                && $period->status !== 'closed'
                                && (
                                    in_array($period->status, ['calculated', 'approved'], true)
                                    || ($summary['success_count'] ?? 0) > 0
                                );
                        @endphp
                        <button
                            type="button"
                            wire:click="confirmPayrollAndNext"
                            wire:confirm="دوره حقوق تایید شود و به مرحله پرداخت برویم؟"
                            wire:loading.attr="disabled"
                            wire:target="confirmPayrollAndNext"
                            @disabled(! $canConfirmPayroll)
                            class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                        >
                            <span wire:loading.remove wire:target="confirmPayrollAndNext">تایید حقوق و رفتن به پرداخت</span>
                            <span wire:loading wire:target="confirmPayrollAndNext">در حال تایید…</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Step 3: Payments --}}
    @if($step === 3)
        <div class="rounded-lg bg-white p-3 shadow-md sm:p-4 space-y-3">
            <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-base font-bold text-slate-900">مرحله ۳ — پرداخت حقوق</h3>
                    <p class="mt-0.5 text-xs text-slate-500">ثبت یا ویرایش پرداخت هر نفر از پاپ‌آپ انجام می‌شود.</p>
                </div>
                @if($summary['workflow_complete'])
                    <div class="rounded bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800">
                        پرداخت‌ها کامل شد
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <div class="text-xs text-slate-500">خالص کل</div>
                    <div class="mt-1 text-lg font-bold tabular-nums text-slate-900">{{ formatMoney($summary['net_total']) }}</div>
                </div>
                <div class="rounded-lg border border-emerald-100 bg-emerald-50 p-3">
                    <div class="text-xs text-emerald-700">پرداخت‌شده</div>
                    <div class="mt-1 text-lg font-bold tabular-nums text-emerald-800">{{ formatMoney($summary['paid_total']) }}</div>
                </div>
                <div class="rounded-lg border border-blue-100 bg-blue-50 p-3">
                    <div class="text-xs text-blue-700">مانده</div>
                    <div class="mt-1 text-lg font-bold tabular-nums text-blue-800">{{ formatMoney($summary['remaining_total']) }}</div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <div class="text-xs text-slate-500">تسویه / باز</div>
                    <div class="mt-1 text-lg font-bold tabular-nums text-slate-900">{{ formatMoney($summary['paid_count']) }} / {{ formatMoney($summary['unpaid_count']) }}</div>
                </div>
            </div>

            <div class="overflow-hidden rounded-md border border-slate-200">
                <div class="divide-y divide-slate-100">
                    @forelse($calculations->where('status', '!=', 'failed') as $calculation)
                        @php
                            $paid = (float) ($calculation->payments_sum_amount ?? $calculation->payments->sum('amount'));
                            $remaining = max(0, (float) $calculation->net_payable - $paid);
                            $paymentRecord = $calculation->payments->first();
                            $isPaid = $remaining <= 0;
                            $statusLabel = $isPaid ? 'پرداخت‌شده' : 'در انتظار';
                            $statusClass = $isPaid ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-800';
                            $canOpenPay = $canPay && $period && $period->status === 'approved';
                            $canReverse = $canPay && $paid > 0 && $paymentRecord && $period && $period->status !== 'closed';
                        @endphp
                        <div wire:key="pay-row-{{ $calculation->id }}" class="flex items-center gap-3 bg-white px-3 py-2 hover:bg-slate-50/80">
                            <span class="inline-flex w-[4.75rem] shrink-0 justify-center rounded px-1.5 py-0.5 text-[10px] font-semibold leading-4 {{ $statusClass }}">
                                {{ $statusLabel }}
                            </span>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                    <span class="text-sm font-semibold leading-5 text-slate-900">{{ $calculation->employee?->full_name }}</span>
                                    @if($paymentRecord)
                                        <span class="text-[11px] text-slate-400">
                                            {{ formatJalaliDateSafe($paymentRecord->payment_date) }}
                                            ·
                                            {{ $paymentRecord->method === 'cash' ? 'نقدی' : 'بانکی' }}
                                        </span>
                                    @endif
                                </div>
                                <div class="mt-1 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-xs text-slate-600">
                                    <span>
                                        <span class="text-[10px] text-slate-400">خالص</span>
                                        <span class="ms-1 font-semibold tabular-nums text-slate-800">{{ formatMoney((float) $calculation->net_payable) }}</span>
                                    </span>
                                    <span class="select-none text-slate-300" aria-hidden="true">┊</span>
                                    <span>
                                        <span class="text-[10px] text-slate-400">پرداخت‌شده</span>
                                        <span class="ms-1 font-semibold tabular-nums text-emerald-800">{{ formatMoney($paid) }}</span>
                                    </span>
                                    <span class="select-none text-slate-300" aria-hidden="true">┊</span>
                                    <span>
                                        <span class="text-[10px] {{ $remaining > 0 ? 'text-blue-600' : 'text-slate-400' }}">مانده</span>
                                        <span class="ms-1 font-bold tabular-nums {{ $remaining > 0 ? 'text-blue-800' : 'text-slate-500' }}">{{ formatMoney($remaining) }}</span>
                                    </span>
                                </div>
                            </div>

                            <div class="flex shrink-0 flex-wrap items-center justify-end gap-1.5">
                                @if($canOpenPay && $remaining > 0)
                                    <button
                                        type="button"
                                        wire:click="openPaymentModal({{ $calculation->id }})"
                                        class="rounded-md bg-emerald-600 px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-emerald-700"
                                    >
                                        ثبت پرداخت
                                    </button>
                                @elseif($canOpenPay && $paid > 0)
                                    <button
                                        type="button"
                                        wire:click="openPaymentModal({{ $calculation->id }})"
                                        class="rounded-md border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-800 hover:bg-emerald-100"
                                    >
                                        ویرایش پرداخت
                                    </button>
                                @endif
                                @if($canReverse)
                                    <button
                                        type="button"
                                        wire:click="reversePayment({{ $calculation->id }})"
                                        wire:confirm="پرداخت {{ $calculation->employee?->full_name }} برگشت داده شود؟"
                                        class="rounded-md border border-red-200 px-2.5 py-1 text-[11px] font-semibold text-red-700 hover:bg-red-50"
                                    >
                                        برگشت
                                    </button>
                                @endif
                                <x-erp.ui.row-actions class="!gap-0.5">
                                    @if($calculation->payslip)
                                        <x-erp.ui.row-action icon="print" label="چاپ فیش" :href="route('payslips.print', $calculation->payslip)" target="_blank" />
                                    @endif
                                </x-erp.ui.row-actions>
                            </div>
                        </div>
                    @empty
                        <div class="px-3 py-6 text-center text-xs text-slate-500">فیش قابل پرداختی وجود ندارد.</div>
                    @endforelse
                </div>
            </div>

            <div class="flex flex-wrap justify-between gap-2 border-t border-slate-100 pt-3">
                <button type="button" wire:click="goToStep(2)" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    بازگشت به محاسبه حقوق
                </button>
                <div class="flex flex-wrap gap-2">
                    @if($canClosePeriod && $period?->status === 'approved' && $summary['workflow_complete'])
                        <button type="button" wire:click="closePeriod" wire:confirm="همه پرداخت‌ها انجام شده. دوره بسته شود؟" class="rounded-md bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-700">
                            اتمام و بستن دوره
                        </button>
                    @endif
                    @if($canClosePeriod && $period?->status === 'closed')
                        <button type="button" wire:click="reopenPeriod" wire:confirm="دوره بسته‌شده باز شود؟" class="rounded-md border border-amber-300 px-3 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-50">
                            بازگشایی دوره
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if($viewingDocument)
        <x-erp.ui.details-modal :title="'سند حسابداری ' . $viewingDocument->number">
            <x-slot name="close">
                <button type="button" class="erp-modal-close" wire:click="closeAccountingDocument">×</button>
            </x-slot>
            <x-slot name="footer">
                <button type="button" class="erp-action-btn" wire:click="closeAccountingDocument">بستن</button>
                <a href="{{ route('accounting-documents.print', ['accountingDocument' => $viewingDocument->getKey()]) }}" target="_blank" class="erp-action-btn erp-action-edit">چاپ</a>
            </x-slot>

            <div class="space-y-3 text-sm">
                <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
                    <div class="rounded-md bg-slate-50 px-2.5 py-2">
                        <div class="text-[10px] text-slate-500">تاریخ</div>
                        <div class="mt-0.5 font-semibold text-slate-800">{{ gregorianToJalaliDate($viewingDocument->document_date) }}</div>
                    </div>
                    <div class="rounded-md bg-slate-50 px-2.5 py-2">
                        <div class="text-[10px] text-slate-500">وضعیت</div>
                        <div class="mt-0.5 font-semibold text-slate-800">{{ $viewingDocument->status === 'posted' ? 'ثبت قطعی' : ($viewingDocument->status === 'draft' ? 'پیش‌نویس' : $viewingDocument->status) }}</div>
                    </div>
                    <div class="rounded-md bg-slate-50 px-2.5 py-2 md:col-span-2">
                        <div class="text-[10px] text-slate-500">شرح</div>
                        <div class="mt-0.5 font-semibold text-slate-800">{{ $viewingDocument->description ?: '-' }}</div>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-md border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-xs">
                        <thead class="bg-slate-50">
                        <tr>
                            <th class="px-2 py-2 text-right font-semibold text-slate-600">حساب</th>
                            <th class="px-2 py-2 text-right font-semibold text-slate-600">شخص</th>
                            <th class="px-2 py-2 text-right font-semibold text-slate-600">شرح</th>
                            <th class="px-2 py-2 text-right font-semibold text-slate-600">بدهکار</th>
                            <th class="px-2 py-2 text-right font-semibold text-slate-600">بستانکار</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($viewingDocument->lines as $line)
                            <tr>
                                <td class="px-2 py-2 text-slate-800">{{ chartAccountDisplayLabel($line->account) }}</td>
                                <td class="px-2 py-2 text-slate-700">{{ $line->party?->name ?: '-' }}</td>
                                <td class="px-2 py-2 text-slate-600">{{ $line->description ?: '-' }}</td>
                                <td class="px-2 py-2 tabular-nums text-slate-800">{{ formatMoney((float) $line->debit) }}</td>
                                <td class="px-2 py-2 tabular-nums text-slate-800">{{ formatMoney((float) $line->credit) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-2 py-6 text-center text-slate-500">ردیفی ثبت نشده است.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </x-erp.ui.details-modal>
    @endif

    @if($editingId)
        <x-erp.ui.details-modal :title="'ویرایش مبالغ حقوق — ' . $editEmployeeName">
            <x-slot name="close">
                <button type="button" class="erp-modal-close" wire:click="cancelEdit">×</button>
            </x-slot>
            <x-slot name="footer">
                <button type="button" class="erp-action-btn" wire:click="cancelEdit">انصراف</button>
                <button type="button" class="erp-action-btn erp-action-edit" wire:click="saveEdit" wire:loading.attr="disabled" wire:target="saveEdit">
                    <span wire:loading.remove wire:target="saveEdit">ذخیره اصلاحات</span>
                    <span wire:loading wire:target="saveEdit">در حال ذخیره…</span>
                </button>
            </x-slot>

            <div class="space-y-4" data-erp-skip-money>
                @if ($errors->any())
                    <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    @foreach($editLines as $index => $line)
                        <label class="block rounded-md border border-slate-200 bg-white p-3 text-xs" wire:key="edit-line-{{ $editingId }}-{{ $line['id'] ?? $index }}">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-semibold text-slate-800">{{ $line['title'] ?? '—' }}</span>
                                <span class="rounded-full px-2 py-0.5 {{ ($line['type'] ?? '') === 'earning' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">
                                    {{ ($line['type'] ?? '') === 'earning' ? 'مزایا' : 'کسورات' }}
                                </span>
                            </div>
                            @if(!empty($line['locked']))
                                <input type="text" readonly dir="ltr" data-erp-money="0" value="{{ moneyInputValue($line['amount'] ?? 0) }}" class="mt-2 w-full rounded-md border-gray-300 bg-slate-100 text-sm text-slate-500 tabular-nums">
                                <div class="mt-1 text-[11px] text-slate-500">بیمه/مالیات سیستمی است و اینجا قفل است.</div>
                            @else
                                <div class="mt-2">
                                    <x-erp.ui.money-input wire:model.blur="editLines.{{ $index }}.amount" class="mt-0" />
                                </div>
                            @endif
                        </label>
                    @endforeach
                </div>

                <div class="grid grid-cols-1 gap-3 border-t border-slate-100 pt-4 md:grid-cols-3">
                    <label class="block text-xs font-medium text-slate-700">
                        افزودن پاداش
                        <div class="mt-1">
                            <x-erp.ui.money-input wire:model.blur="newBonusAmount" />
                        </div>
                    </label>
                    <label class="block text-xs font-medium text-slate-700">
                        عنوان کسورات دستی
                        <input type="text" wire:model.blur="newDeductionTitle" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                    </label>
                    <label class="block text-xs font-medium text-slate-700">
                        مبلغ کسورات دستی
                        <div class="mt-1">
                            <x-erp.ui.money-input wire:model.blur="newDeductionAmount" />
                        </div>
                    </label>
                </div>
            </div>
        </x-erp.ui.details-modal>
    @endif

    @if($paymentModalCalculationId && $modalDraftKey)
        <x-erp.ui.details-modal :title="(($paymentDrafts[$modalDraftKey]['is_edit'] ?? false) ? 'ویرایش پرداخت — ' : 'ثبت پرداخت — ') . $paymentModalEmployeeName">
            <x-slot name="close">
                <button type="button" class="erp-modal-close" wire:click="closePaymentModal">×</button>
            </x-slot>
            <x-slot name="footer">
                <button type="button" class="erp-action-btn" wire:click="closePaymentModal">انصراف</button>
                <button type="button" class="erp-action-btn erp-action-edit" wire:click="savePaymentModal" wire:loading.attr="disabled" wire:target="savePaymentModal">
                    <span wire:loading.remove wire:target="savePaymentModal">ذخیره پرداخت</span>
                    <span wire:loading wire:target="savePaymentModal">در حال ذخیره…</span>
                </button>
            </x-slot>

            <div class="space-y-3">
                @if ($errors->any())
                    <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <label class="block text-sm font-medium text-slate-700">
                        تاریخ پرداخت
                        <div class="mt-1">
                            <x-erp.ui.jalali-date-input wire:model.blur="paymentDrafts.{{ $modalDraftKey }}.payment_date" class="w-full" />
                        </div>
                        @error('paymentDrafts.'.$modalDraftKey.'.payment_date') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block text-sm font-medium text-slate-700">
                        روش پرداخت
                        <select wire:model.live="paymentDrafts.{{ $modalDraftKey }}.method" data-erp-no-lookup="1" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            <option value="bank">بانکی</option>
                            <option value="cash">نقدی</option>
                        </select>
                        @error('paymentDrafts.'.$modalDraftKey.'.method') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>
                    @if(($paymentDrafts[$modalDraftKey]['method'] ?? 'bank') === 'bank')
                        <label class="block text-sm font-medium text-slate-700 md:col-span-2">
                            حساب بانکی پرداخت‌کننده
                            <select wire:model="paymentDrafts.{{ $modalDraftKey }}.bank_account_id" data-erp-no-lookup="1" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                <option value="">انتخاب کنید</option>
                                @foreach($bankAccounts as $bank)
                                    <option value="{{ $bank->id }}">{{ $bank->bank_name }} — {{ $bank->account_number ?: $bank->code }}</option>
                                @endforeach
                            </select>
                            @error('paymentDrafts.'.$modalDraftKey.'.bank_account_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                        </label>
                    @else
                        <label class="block text-sm font-medium text-slate-700 md:col-span-2">
                            صندوق
                            <select wire:model="paymentDrafts.{{ $modalDraftKey }}.cashbox_id" data-erp-no-lookup="1" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                <option value="">انتخاب کنید</option>
                                @foreach($cashboxes as $cashbox)
                                    <option value="{{ $cashbox->id }}">{{ $cashbox->name }}</option>
                                @endforeach
                            </select>
                            @error('paymentDrafts.'.$modalDraftKey.'.cashbox_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                        </label>
                    @endif
                    <label class="block text-sm font-medium text-slate-700">
                        مبلغ پرداخت
                        <div class="mt-1">
                            <x-erp.ui.money-input wire:model.blur="paymentDrafts.{{ $modalDraftKey }}.amount" />
                        </div>
                        @error('paymentDrafts.'.$modalDraftKey.'.amount') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block text-sm font-medium text-slate-700">
                        شماره پیگیری
                        <input type="text" wire:model.blur="paymentDrafts.{{ $modalDraftKey }}.reference_number" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                    </label>
                    <label class="block text-sm font-medium text-slate-700 md:col-span-2">
                        شرح
                        <input type="text" wire:model.blur="paymentDrafts.{{ $modalDraftKey }}.description" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                    </label>
                </div>

                @if($paymentDrafts[$modalDraftKey]['is_edit'] ?? false)
                    <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-6 text-amber-800">
                        با ذخیره ویرایش، پرداخت قبلی برگشت می‌خورد و پرداخت جدید با مبلغ/حساب انتخاب‌شده ثبت می‌شود.
                    </div>
                @endif
            </div>
        </x-erp.ui.details-modal>
    @endif
</div>
