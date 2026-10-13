@php
    $fmtMinutes = static function (int $minutes): string {
        if ($minutes <= 0) {
            return '-';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        if ($h > 0 && $m > 0) {
            return toPersianDigits($h) . ':' . toPersianDigits(str_pad((string) $m, 2, '0', STR_PAD_LEFT));
        }
        if ($h > 0) {
            return toPersianDigits($h) . 'س';
        }
        return toPersianDigits($m) . 'د';
    };
@endphp

<div dir="rtl" class="space-y-4">
    @include('livewire.partials.flash')

    <div class="rounded-lg bg-white p-4 shadow-md sm:p-5">
        <div class="mb-3">
            <h3 class="text-lg font-bold text-slate-900">خلاصه کارکرد روزانه</h3>
            <p class="mt-1 text-sm text-slate-500">پرسنل و ماه را انتخاب کنید؛ روزهای تعطیل قرمز و تردد ناقص مشخص می‌شود.</p>
        </div>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
            <label class="block">
                <span class="text-sm font-medium text-slate-700">پرسنل</span>
                <select wire:model.live="employee_id" class="mt-1 w-full rounded-md border-gray-300 text-right">
                    <option value="">انتخاب کنید</option>
                    @foreach($employees as $item)
                        <option value="{{ $item->id }}">{{ $item->personnel_code }} — {{ $item->full_name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="text-sm font-medium text-slate-700">سال</span>
                <select wire:model.live="year" class="mt-1 w-full rounded-md border-gray-300 text-right" data-erp-money="0">
                    @for($y = (int) getCurrentPersianYear() + 1; $y >= 1400; $y--)
                        <option value="{{ $y }}">{{ toPersianDigits($y) }}</option>
                    @endfor
                </select>
            </label>

            <label class="block">
                <span class="text-sm font-medium text-slate-700">ماه</span>
                <select wire:model.live="month" class="mt-1 w-full rounded-md border-gray-300 text-right" data-erp-money="0">
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}">{{ getPersianMonthName($m) }}</option>
                    @endfor
                </select>
            </label>
        </div>
    </div>

    <div class="rounded-lg bg-white p-4 shadow-md sm:p-5" wire:loading.class="opacity-60">
        @if(! $employee_id)
            <div class="py-10 text-center text-slate-500">ابتدا پرسنل را انتخاب کنید.</div>
        @elseif(empty($days))
            <div class="py-10 text-center text-slate-500">برای این ماه ردیفی پیدا نشد.</div>
        @else
            <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <h3 class="text-base font-bold text-slate-900">
                    {{ $employee?->full_name }} — {{ getPersianMonthName($month) }} {{ toPersianDigits($year) }}
                </h3>
                <div class="flex flex-wrap items-center gap-3 text-xs">
                    <span class="font-semibold text-red-600">قرمز: تعطیل رسمی / پنجشنبه و جمعه</span>
                    <span class="font-semibold text-orange-600">نارنجی: غیبت</span>
                    <span class="font-semibold text-amber-700">کهربایی: تردد ناقص</span>
                    <div wire:loading.delay class="text-slate-500">در حال بارگذاری...</div>
                </div>
            </div>

            <div class="table-responsive overflow-x-auto">
                <table class="erp-ui-data-table daily-att-table min-w-full text-sm">
                    <thead>
                    <tr>
                        <th>تاریخ</th>
                        <th>نام روز</th>
                        <th>نوع روز</th>
                        <th>شروع</th>
                        <th>پایان</th>
                        <th>تأخیر</th>
                        <th>تعجیل</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($days as $day)
                        @php
                            $rowClass = match (true) {
                                (bool) ($day['is_off_day'] ?? $day['is_holiday'] ?? false) => 'daily-att-row--off',
                                (bool) ($day['is_absence'] ?? false) => 'daily-att-row--absence',
                                (bool) ($day['is_incomplete'] ?? false) => 'daily-att-row--incomplete',
                                default => '',
                            };
                        @endphp
                        <tr class="{{ $rowClass }}" wire:key="day-{{ $day['work_date'] }}">
                            <td class="text-nowrap">{{ toPersianDigits($day['work_date_jalali']) }}</td>
                            <td>{{ $day['day_name'] }}</td>
                            <td class="font-semibold">{{ $day['day_type'] }}</td>
                            <td dir="ltr">{{ $day['first_in'] !== '-' ? $day['first_in'] : $day['shift_start'] }}</td>
                            <td dir="ltr">{{ $day['last_out'] !== '-' ? $day['last_out'] : $day['shift_end'] }}</td>
                            <td>{{ $fmtMinutes($day['delay_minutes']) }}</td>
                            <td>{{ $fmtMinutes($day['early_leave_minutes']) }}</td>
                            <td>
                                @if($day['is_off_day'] ?? $day['is_holiday'] ?? false)
                                    <span class="daily-att-badge daily-att-badge--off">{{ $day['day_type'] }}</span>
                                @elseif($day['is_absence'] ?? false)
                                    <span class="daily-att-badge daily-att-badge--absence">غیبت</span>
                                @elseif($day['is_incomplete'])
                                    <span class="daily-att-badge daily-att-badge--incomplete">تردد ناقص</span>
                                @elseif($day['first_in'] !== '-' && $day['last_out'] !== '-')
                                    <span class="daily-att-badge daily-att-badge--ok">حاضر</span>
                                @else
                                    <span class="daily-att-badge">—</span>
                                @endif
                            </td>
                            <td>
                                <x-erp.ui.row-actions>
                                    <x-erp.ui.row-action
                                        icon="edit"
                                        label="ویرایش تردد"
                                        wire:click="openEdit('{{ $day['work_date'] }}', '{{ $day['work_date_jalali'] }}', {{ (int) ($day['work_log_id'] ?? 0) }}, '{{ $day['first_in'] }}', '{{ $day['last_out'] }}')"
                                    />
                                    <x-erp.ui.row-action
                                        icon="document"
                                        label="ثبت مرخصی"
                                        wire:click="openLeave('{{ $day['work_date'] }}', '{{ $day['work_date_jalali'] }}')"
                                    />
                                    <x-erp.ui.row-action
                                        icon="project"
                                        label="ثبت ماموریت"
                                        wire:click="openMission('{{ $day['work_date'] }}', '{{ $day['work_date_jalali'] }}')"
                                    />
                                </x-erp.ui.row-actions>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <style>
        .daily-att-table.erp-ui-data-table th,
        .daily-att-table.erp-ui-data-table td {
            padding: .28rem .4rem !important;
            font-size: .78rem !important;
            line-height: 1.25 !important;
            vertical-align: middle !important;
        }
        .daily-att-table.erp-ui-data-table tbody tr {
            height: auto !important;
        }
        .daily-att-row--off,
        .daily-att-row--off td {
            color: #dc2626 !important;
            background: transparent !important;
            font-weight: 600;
        }
        .daily-att-row--absence,
        .daily-att-row--absence td {
            color: #ea580c !important;
            background: transparent !important;
            font-weight: 600;
        }
        .daily-att-row--incomplete,
        .daily-att-row--incomplete td {
            color: #b45309 !important;
            background: transparent !important;
            font-weight: 600;
        }
        .daily-att-badge {
            display: inline;
            padding: 0;
            font-size: inherit;
            font-weight: 700;
            white-space: nowrap;
            background: transparent !important;
        }
        .daily-att-badge--off { color: #dc2626; }
        .daily-att-badge--absence { color: #ea580c; }
        .daily-att-badge--incomplete { color: #b45309; }
        .daily-att-badge--ok { color: #15803d; }
        .daily-att-table .erp-row-actions {
            gap: .05rem !important;
            display: inline-flex !important;
            align-items: center !important;
        }
        .erp-shell .daily-att-table .erp-row-action-btn,
        .erp-shell main .daily-att-table .erp-row-action-btn,
        .daily-att-table .erp-row-action-btn {
            width: auto !important;
            height: auto !important;
            min-width: 0 !important;
            max-width: none !important;
            min-height: 0 !important;
            padding: .05rem !important;
            border: 0 !important;
            border-radius: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
            color: #64748b !important;
            gap: 0 !important;
        }
        .erp-shell .daily-att-table .erp-row-action-btn:hover,
        .erp-shell main .daily-att-table .erp-row-action-btn:hover,
        .daily-att-table .erp-row-action-btn:hover {
            border: 0 !important;
            background: transparent !important;
            color: #0f172a !important;
            transform: none !important;
            box-shadow: none !important;
        }
        .erp-shell .daily-att-table .erp-row-action-btn svg,
        .erp-shell main .daily-att-table .erp-row-action-btn svg,
        .daily-att-table .erp-row-action-btn svg,
        .daily-att-table .erp-action-icon {
            width: .78rem !important;
            height: .78rem !important;
        }
    </style>

    @if($showLeaveModal)
        <div class="erp-modal">
            <div class="erp-ui-modal-backdrop" wire:click="closeModals"></div>
            <div class="erp-ui-modal-panel" style="max-width:28rem">
                <div class="erp-modal-header">
                    <h3>ثبت مرخصی — {{ toPersianDigits($actionDateJalali) }}</h3>
                    <button type="button" class="erp-modal-close" wire:click="closeModals" aria-label="بستن">×</button>
                </div>
                <div class="erp-modal-body space-y-3">
                    <label class="block">نوع
                        <select wire:model.live="leave_request_type" class="mt-1 w-full rounded-md border-gray-300">
                            <option value="hourly">ساعتی</option>
                            <option value="daily">روزانه</option>
                        </select>
                    </label>
                    @if($leave_request_type === 'hourly')
                        <div class="grid grid-cols-2 gap-3">
                            <label class="block">شروع مرخصی
                                <input type="time" wire:model="leave_start_time" class="mt-1 w-full rounded-md border-gray-300" dir="ltr">
                                @error('leave_start_time') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                            </label>
                            <label class="block">پایان مرخصی
                                <input type="time" wire:model="leave_end_time" class="mt-1 w-full rounded-md border-gray-300" dir="ltr">
                                @error('leave_end_time') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                            </label>
                        </div>
                    @endif
                    <label class="block">نوع مرخصی
                        <input wire:model="leave_type" class="mt-1 w-full rounded-md border-gray-300">
                    </label>
                    <label class="block">علت
                        <input wire:model="leave_reason" class="mt-1 w-full rounded-md border-gray-300">
                    </label>
                </div>
                <div class="erp-modal-actions">
                    <button type="button" class="erp-action-btn" wire:click="closeModals">انصراف</button>
                    <button type="button" class="erp-action-btn erp-action-edit" wire:click="saveLeave">ثبت</button>
                </div>
            </div>
        </div>
    @endif

    @if($showMissionModal)
        <div class="erp-modal">
            <div class="erp-ui-modal-backdrop" wire:click="closeModals"></div>
            <div class="erp-ui-modal-panel" style="max-width:28rem">
                <div class="erp-modal-header">
                    <h3>ثبت ماموریت — {{ toPersianDigits($actionDateJalali) }}</h3>
                    <button type="button" class="erp-modal-close" wire:click="closeModals" aria-label="بستن">×</button>
                </div>
                <div class="erp-modal-body space-y-3">
                    <label class="block">نوع
                        <select wire:model.live="mission_request_type" class="mt-1 w-full rounded-md border-gray-300">
                            <option value="daily">روزانه</option>
                            <option value="hourly">ساعتی</option>
                        </select>
                    </label>
                    @if($mission_request_type === 'hourly')
                        <label class="block">ساعت
                            <input type="number" step="0.25" wire:model="mission_hours" class="mt-1 w-full rounded-md border-gray-300">
                        </label>
                    @endif
                    <label class="block">توضیح
                        <input wire:model="mission_description" class="mt-1 w-full rounded-md border-gray-300">
                    </label>
                </div>
                <div class="erp-modal-actions">
                    <button type="button" class="erp-action-btn" wire:click="closeModals">انصراف</button>
                    <button type="button" class="erp-action-btn erp-action-edit" wire:click="saveMission">ثبت</button>
                </div>
            </div>
        </div>
    @endif

    @if($showEditModal)
        <div class="erp-modal">
            <div class="erp-ui-modal-backdrop" wire:click="closeModals"></div>
            <div class="erp-ui-modal-panel" style="max-width:28rem">
                <div class="erp-modal-header">
                    <h3>ویرایش تردد — {{ toPersianDigits($actionDateJalali) }}</h3>
                    <button type="button" class="erp-modal-close" wire:click="closeModals" aria-label="بستن">×</button>
                </div>
                <div class="erp-modal-body space-y-3">
                    <label class="block">ساعت شروع
                        <input type="time" wire:model="edit_start_time" class="mt-1 w-full rounded-md border-gray-300" dir="ltr">
                        @error('edit_start_time') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block">ساعت پایان
                        <input type="time" wire:model="edit_end_time" class="mt-1 w-full rounded-md border-gray-300" dir="ltr">
                        @error('edit_end_time') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>
                </div>
                <div class="erp-modal-actions">
                    <button type="button" class="erp-action-btn" wire:click="closeModals">انصراف</button>
                    <button type="button" class="erp-action-btn erp-action-edit" wire:click="saveEdit">ذخیره</button>
                </div>
            </div>
        </div>
    @endif
</div>
