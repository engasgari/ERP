<div dir="rtl" class="space-y-6">
    @include('livewire.partials.flash')

    <div class="rounded-lg bg-white p-4 shadow-md sm:p-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div class="max-w-3xl">
                <h3 class="text-lg font-bold text-slate-900">محاسبه کارکرد</h3>
                <p class="mt-1 text-sm leading-7 text-slate-500">
                    ابتدا کارکرد را محاسبه کنید؛ سپس می‌توانید برای هر نفر روز کارکرد، روز غیبت، تأخیر، تعجیل، اضافه‌کاری، ماموریت و سایر موارد را دستی اصلاح کنید.
                </p>
                <div class="mt-3 grid grid-cols-1 gap-2 text-xs leading-6 text-slate-600 md:grid-cols-2">
                    <div class="rounded-md bg-slate-50 p-3">
                        <span class="font-semibold text-slate-800">ماهانه:</span>
                        ساعت موظف از تقویم کاری و شیفت می‌آید؛ بعد از محاسبه، ویرایش دستی روی همان رکورد ذخیره می‌شود.
                    </div>
                    <div class="rounded-md bg-emerald-50 p-3 text-emerald-800">
                        <span class="font-semibold">نکته:</span>
                        محاسبه مجدد کارکرد، مقادیر سیستمی را بازنویسی می‌کند. اگر دستی اصلاح کرده‌اید، بعد از محاسبه دوباره ویرایش کنید.
                    </div>
                </div>
            </div>

            <form wire:submit.prevent="calculate" class="grid grid-cols-1 gap-3 sm:grid-cols-6 xl:min-w-[900px]">
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">سال</span>
                    <select wire:model.live="year" class="mt-1 w-full rounded-md border-gray-300 text-right">
                        @for($y = (int) getCurrentPersianYear(); $y >= 1400; $y--)
                            <option value="{{ $y }}" @selected((int) $year === $y)>{{ toPersianDigits($y) }}</option>
                        @endfor
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">ماه</span>
                    <select wire:model.live="month" class="mt-1 w-full rounded-md border-gray-300 text-right">
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" @selected((int) $month === $m)>{{ getPersianMonthName($m) }}</option>
                        @endfor
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">نوع</span>
                    <select wire:model.live="salaryType" class="mt-1 w-full rounded-md border-gray-300 text-right">
                        <option value="">همه</option>
                        <option value="hourly">ساعتی</option>
                        <option value="monthly">ماهانه</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">دامنه</span>
                    <select wire:model.live="scope" class="mt-1 w-full rounded-md border-gray-300 text-right">
                        <option value="with_data">دارای داده کارکرد</option>
                        <option value="all">همه پرسنل فعال</option>
                        <option value="single">یک پرسنل</option>
                    </select>
                </label>

                @if($scope === 'single')
                    <label class="block sm:col-span-2">
                        <span class="text-sm font-medium text-slate-700">پرسنل</span>
                        <select wire:model="employee_id" class="mt-1 w-full rounded-md border-gray-300 text-right">
                            <option value="">انتخاب کنید</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->employee_code }} - {{ $employee->full_name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">جستجو</span>
                    <input wire:model.live.debounce.400ms="search" placeholder="نام یا کد پرسنلی" class="mt-1 w-full rounded-md border-gray-300 text-right">
                </label>

                <div class="flex items-end">
                    <button type="submit" wire:loading.attr="disabled" wire:target="calculate" class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-60">
                        محاسبه کارکرد
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="rounded-lg bg-white p-4 shadow-md sm:p-6">
        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-bold text-slate-900">لیست محاسبه کارکردها</h3>
                <p class="mt-1 text-sm text-slate-500">
                    دوره انتخابی: {{ getPersianMonthName($month) }} {{ toPersianDigits($year) }}
                    @if($period)
                        ، از {{ formatJalaliDateSafe($period->starts_at) }} تا {{ formatJalaliDateSafe($period->ends_at) }}
                    @endif
                </p>
            </div>
            <div wire:loading.delay class="text-sm text-slate-500">در حال به‌روزرسانی...</div>
        </div>

        @if($errors->any())
            <div class="mb-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="erp-ui-data-table min-w-full">
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
                    <th>عملیات</th>
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
                        <td class="font-semibold text-slate-900">{{ $calculation->employee?->full_name }}</td>
                        <td>
                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $type === 'hourly' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800' }}">
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
                                <span class="inline-flex rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800">اصلاح دستی</span>
                            @else
                                <span class="inline-flex rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">سیستمی</span>
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
                        <tr class="bg-slate-50" wire:key="attendance-edit-{{ $calculation->id }}">
                            <td colspan="15" class="p-4">
                                <div class="mb-3 text-sm font-semibold text-slate-800">
                                    ویرایش کارکرد: {{ $calculation->employee?->full_name }}
                                </div>
                                <div class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-6">
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
                                        <label class="block text-xs font-medium text-slate-700">
                                            {{ $label }}
                                            <input type="number" step="0.01" min="0" wire:model.blur="editForm.{{ $field }}" data-erp-money="0" data-erp-number="0" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                            @error('editForm.'.$field) <span class="text-red-600">{{ $message }}</span> @enderror
                                        </label>
                                    @endforeach
                                </div>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <button type="button" wire:click="saveEdit(false)" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                                        ذخیره
                                    </button>
                                    <button type="button" wire:click="saveEdit(true)" class="rounded-md border border-emerald-600 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">
                                        محاسبه مجدد قابل‌پرداخت و ذخیره
                                    </button>
                                    <button type="button" wire:click="cancelEdit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-white">
                                        انصراف
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="15" class="py-8 text-center text-slate-500">برای این دوره هنوز محاسبه کارکردی وجود ندارد.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $calculations->links() }}</div>
    </div>
</div>
