<div>
    <x-erp.ui.list-page
        title="محاسبه کارکرد"
        description="برای ماه انتخاب‌شده، کارکرد پرسنل را از روی ترددها محاسبه کنید. بعد از محاسبه می‌توانید اعداد هر نفر را دستی اصلاح کنید."
        route="attendance.calculations"
        panel-class="attendance-calc-page"
    >
        <x-slot name="filters">
            <x-erp.ui.filter-bar wire:submit.prevent="calculate">
                <div class="erp-filter-row erp-filter-row-5">
                    <label class="erp-filter-field">سال
                        <select wire:model.live="year">
                            @for($y = (int) getCurrentPersianYear(); $y >= 1400; $y--)
                                <option value="{{ $y }}" @selected((int) $year === $y)>{{ toPersianDigits($y) }}</option>
                            @endfor
                        </select>
                    </label>

                    <label class="erp-filter-field">ماه
                        <select wire:model.live="month">
                            @for($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}" @selected((int) $month === $m)>{{ getPersianMonthName($m) }}</option>
                            @endfor
                        </select>
                    </label>

                    <label class="erp-filter-field">نوع حقوق
                        <select wire:model.live="salaryType">
                            <option value="">همه</option>
                            <option value="hourly">ساعتی</option>
                            <option value="monthly">ماهانه</option>
                        </select>
                    </label>

                    <label class="erp-filter-field">دامنه محاسبه
                        <select wire:model.live="scope">
                            <option value="with_data">دارای داده کارکرد</option>
                            <option value="all">همه پرسنل فعال</option>
                            <option value="single">یک پرسنل</option>
                        </select>
                    </label>

                    <label class="erp-filter-field">جستجو در لیست
                        <input wire:model.live.debounce.400ms="search" placeholder="نام یا کد پرسنلی" type="text">
                    </label>
                </div>

                @if($scope === 'single')
                    <div class="erp-filter-row erp-filter-row-5 mt-2">
                        <label class="erp-filter-field col-span-2">پرسنل
                            <select wire:model="employee_id">
                                <option value="">انتخاب کنید</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}">
                                        {{ toPersianDigits($employee->employee_code ?: $employee->personnel_code) }}
                                        — {{ $employee->full_name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                @endif

                <div class="erp-filter-actions mt-2">
                    <button
                        type="submit"
                        class="erp-action-btn erp-action-primary"
                        wire:loading.attr="disabled"
                        wire:target="calculate"
                    >
                        <span wire:loading.remove wire:target="calculate">محاسبه کارکرد</span>
                        <span wire:loading wire:target="calculate">در حال محاسبه…</span>
                    </button>
                </div>
            </x-erp.ui.filter-bar>

            <div class="attendance-calc-hint">
                <span>محاسبه مجدد، مقادیر سیستمی را بازنویسی می‌کند.</span>
                <span>ویرایش دستی بعد از محاسبه روی همان رکورد ذخیره می‌شود.</span>
            </div>
        </x-slot>

        @if($errors->any())
            <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div class="text-sm text-slate-600">
                دوره:
                <strong class="text-slate-900">{{ getPersianMonthName($month) }} {{ toPersianDigits($year) }}</strong>
                @if($period)
                    <span class="text-slate-400">·</span>
                    {{ formatJalaliDateSafe($period->starts_at) }} تا {{ formatJalaliDateSafe($period->ends_at) }}
                @endif
            </div>
            <div class="text-xs text-slate-500">
                {{ toPersianDigits($calculations->total()) }} نفر
            </div>
        </div>

        <div class="erp-table-wrap table-responsive overflow-x-auto">
            <table class="erp-ui-data-table w-full attendance-calc-table">
                <thead>
                <tr>
                    <th>پرسنل</th>
                    <th>نوع</th>
                    <th>روز کارکرد</th>
                    <th>روز غیبت</th>
                    <th>موظف</th>
                    <th>عادی</th>
                    <th>اضافه‌کار</th>
                    <th>تأخیر</th>
                    <th>تعجیل</th>
                    <th>غیبت</th>
                    <th>مرخصی</th>
                    <th>ماموریت</th>
                    <th>قابل پرداخت</th>
                    <th>وضعیت</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($calculations as $calculation)
                    @php
                        $type = $calculation->meta['employee_salary_type'] ?? 'monthly';
                        $isEditing = $editingId === $calculation->id;
                        $isAdjusted = ($calculation->status === 'adjusted') || !empty($calculation->meta['manually_adjusted']);
                        $workDays = (float) ($calculation->work_days ?? $calculation->present_days ?? 0);
                        $absenceDays = (float) ($calculation->absence_days ?? 0);
                    @endphp
                    <tr wire:key="attendance-calculation-{{ $calculation->id }}">
                        <td class="font-semibold text-slate-900 whitespace-nowrap">{{ $calculation->employee?->full_name }}</td>
                        <td>
                            <span @class([
                                'attendance-calc-badge',
                                'attendance-calc-badge--hourly' => $type === 'hourly',
                                'attendance-calc-badge--monthly' => $type !== 'hourly',
                            ])>
                                {{ $type === 'hourly' ? 'ساعتی' : 'ماهانه' }}
                            </span>
                        </td>
                        <td>{{ formatMoney($workDays, 2) }}</td>
                        <td>{{ formatMoney($absenceDays, 2) }}</td>
                        <td>{{ formatMoney((float) $calculation->required_hours, 2) }}</td>
                        <td>{{ formatMoney((float) $calculation->normal_hours, 2) }}</td>
                        <td>{{ formatMoney((float) $calculation->overtime_hours, 2) }}</td>
                        <td>{{ formatMoney((float) $calculation->delay_hours, 2) }}</td>
                        <td>{{ formatMoney((float) $calculation->early_leave_hours, 2) }}</td>
                        <td>{{ formatMoney((float) $calculation->absence_hours, 2) }}</td>
                        <td>{{ formatMoney((float) $calculation->leave_hours, 2) }}</td>
                        <td>{{ formatMoney((float) $calculation->mission_hours, 2) }}</td>
                        <td class="font-semibold text-slate-900">{{ formatMoney((float) $calculation->payable_hours, 2) }}</td>
                        <td>
                            @if($isAdjusted)
                                <span class="attendance-calc-badge attendance-calc-badge--adjusted">اصلاح دستی</span>
                            @else
                                <span class="attendance-calc-badge attendance-calc-badge--system">سیستمی</span>
                            @endif
                        </td>
                        <td>
                            @if(! $isEditing)
                                <x-erp.ui.row-actions>
                                    <x-erp.ui.row-action icon="edit" label="ویرایش" wire:click="startEdit({{ $calculation->id }})" />
                                </x-erp.ui.row-actions>
                            @endif
                        </td>
                    </tr>

                    @if($isEditing)
                        <tr class="attendance-calc-edit-row" wire:key="attendance-edit-{{ $calculation->id }}">
                            <td colspan="15">
                                <div class="attendance-calc-edit">
                                    <div class="attendance-calc-edit__title">
                                        ویرایش کارکرد: {{ $calculation->employee?->full_name }}
                                    </div>
                                    <div class="attendance-calc-edit__grid">
                                        @foreach([
                                            'work_days' => 'روز کارکرد',
                                            'absence_days' => 'روز غیبت',
                                            'normal_hours' => 'کارکرد عادی',
                                            'overtime_hours' => 'اضافه‌کاری',
                                            'delay_hours' => 'تأخیر',
                                            'early_leave_hours' => 'تعجیل',
                                            'absence_hours' => 'غیبت',
                                            'leave_hours' => 'مرخصی',
                                            'mission_hours' => 'ماموریت',
                                            'night_hours' => 'شب‌کاری',
                                            'holiday_hours' => 'تعطیل‌کاری',
                                            'payable_hours' => 'قابل پرداخت',
                                            'net_payable_hours' => 'خالص قابل پرداخت',
                                        ] as $field => $label)
                                            <label class="attendance-calc-edit__field">
                                                {{ $label }}
                                                <input type="number" step="0.01" min="0" wire:model.blur="editForm.{{ $field }}" data-erp-money="0" data-erp-number="0">
                                                @error('editForm.'.$field) <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                            </label>
                                        @endforeach
                                    </div>
                                    <div class="attendance-calc-edit__actions">
                                        <button type="button" wire:click="saveEdit(false)" class="erp-action-btn erp-action-primary">ذخیره</button>
                                        <button type="button" wire:click="saveEdit(true)" class="erp-action-btn erp-action-detail">محاسبه مجدد قابل‌پرداخت و ذخیره</button>
                                        <button type="button" wire:click="cancelEdit" class="erp-action-btn">انصراف</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="15" class="py-8 text-center text-slate-500">
                            برای این دوره هنوز محاسبه کارکردی وجود ندارد. فیلترها را تنظیم کنید و «محاسبه کارکرد» را بزنید.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($calculations->count() > 0)
            <div class="mt-4">{{ $calculations->links() }}</div>
        @endif
    </x-erp.ui.list-page>

    <style>
        .attendance-calc-page .attendance-calc-hint {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem 1rem;
            margin-top: 0.65rem;
            padding: 0.55rem 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            background: #f8fafc;
            color: #64748b;
            font-size: 0.78rem;
            line-height: 1.5;
        }
        .attendance-calc-page .attendance-calc-hint span + span::before {
            content: "·";
            margin-inline-end: 0.75rem;
            color: #94a3b8;
        }
        .attendance-calc-page .attendance-calc-table {
            font-size: 0.8rem;
        }
        .attendance-calc-page .attendance-calc-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.15rem 0.55rem;
            font-size: 0.7rem;
            font-weight: 700;
            white-space: nowrap;
        }
        .attendance-calc-page .attendance-calc-badge--hourly {
            background: #dcfce7;
            color: #166534;
        }
        .attendance-calc-page .attendance-calc-badge--monthly {
            background: #dbeafe;
            color: #1e40af;
        }
        .attendance-calc-page .attendance-calc-badge--adjusted {
            background: #fef3c7;
            color: #92400e;
        }
        .attendance-calc-page .attendance-calc-badge--system {
            background: #f1f5f9;
            color: #475569;
        }
        .attendance-calc-page .attendance-calc-edit-row > td {
            background: #f8fafc !important;
            padding: 0.85rem !important;
        }
        .attendance-calc-page .attendance-calc-edit__title {
            margin-bottom: 0.65rem;
            font-size: 0.85rem;
            font-weight: 700;
            color: #0f172a;
        }
        .attendance-calc-page .attendance-calc-edit__grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.55rem;
        }
        @media (min-width: 768px) {
            .attendance-calc-page .attendance-calc-edit__grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }
        @media (min-width: 1280px) {
            .attendance-calc-page .attendance-calc-edit__grid {
                grid-template-columns: repeat(6, minmax(0, 1fr));
            }
        }
        .attendance-calc-page .attendance-calc-edit__field {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            font-size: 0.72rem;
            font-weight: 600;
            color: #475569;
        }
        .attendance-calc-page .attendance-calc-edit__field input {
            min-height: 2rem;
            border: 1px solid #cbd5e1;
            border-radius: 0.375rem;
            padding: 0.3rem 0.5rem;
            font-size: 0.8rem;
            background: #fff;
        }
        .attendance-calc-page .attendance-calc-edit__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            margin-top: 0.85rem;
        }
    </style>
</div>
