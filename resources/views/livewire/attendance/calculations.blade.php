<div dir="rtl" class="space-y-6">
    @include('livewire.partials.flash')

    <div class="rounded-lg bg-white p-4 shadow-md sm:p-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div class="max-w-3xl">
                <h3 class="text-lg font-bold text-slate-900">محاسبه کارکرد</h3>
                <p class="mt-1 text-sm leading-7 text-slate-500">
                    این بخش فقط کارکرد را محاسبه می‌کند و خروجی آن در لیست پایین ذخیره می‌شود. حقوق و فیش از صفحه دوره‌های حقوق اجرا می‌شوند.
                </p>
                <div class="mt-3 grid grid-cols-1 gap-2 text-xs leading-6 text-slate-600 md:grid-cols-2">
                    <div class="rounded-md bg-slate-50 p-3">
                        <span class="font-semibold text-slate-800">ماهانه:</span>
                        ساعت موظف از تقویم کاری و شیفت می‌آید؛ اضافه‌کاری برابر مازاد کارکرد بر موظفی روز، و غیبت برابر کسری موظفی پس از مرخصی/ماموریت تایید شده است.
                    </div>
                    <div class="rounded-md bg-emerald-50 p-3 text-emerald-800">
                        <span class="font-semibold">ساعتی:</span>
                        موظفی، غیبت، تاخیر، تعجیل، اضافه‌کاری و تعطیل‌کاری محاسبه نمی‌شود؛ کل ساعت واقعی کارکرد به عنوان کارکرد عادی پرداخت می‌شود.
                    </div>
                </div>
            </div>

            <form wire:submit.prevent="calculate" class="grid grid-cols-1 gap-3 sm:grid-cols-6 xl:min-w-[900px]">
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">سال</span>
                    <input type="number" wire:model.live="year" min="1400" max="1500" class="mt-1 w-full rounded-md border-gray-300 text-right">
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
                    دوره انتخابی: {{ getPersianMonthName($month) }} {{ $year }}
                    @if($period)
                        ، از {{ formatJalaliDateSafe($period->starts_at) }} تا {{ formatJalaliDateSafe($period->ends_at) }}
                    @endif
                </p>
            </div>
            <div wire:loading.delay class="text-sm text-slate-500">در حال به‌روزرسانی...</div>
        </div>

        <div class="overflow-x-auto">
            <table class="erp-ui-data-table min-w-full">
                <thead>
                <tr>
                    <th>پرسنل</th>
                    <th>نوع محاسبه</th>
                    <th>موظف</th>
                    <th>کارکرد عادی</th>
                    <th>اضافه‌کاری</th>
                    <th>غیبت</th>
                    <th>مرخصی</th>
                    <th>ماموریت</th>
                    <th>قابل پرداخت</th>
                    <th>مبنای محاسبه</th>
                </tr>
                </thead>
                <tbody>
                @forelse($calculations as $calculation)
                    @php
                        $type = $calculation->meta['employee_salary_type'] ?? 'monthly';
                        $rule = $calculation->meta['calculation_rule'] ?? null;
                    @endphp
                    <tr wire:key="attendance-calculation-{{ $calculation->id }}">
                        <td class="font-semibold text-slate-900">{{ $calculation->employee?->full_name }}</td>
                        <td>
                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $type === 'hourly' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800' }}">
                                {{ $type === 'hourly' ? 'ساعتی' : 'ماهانه' }}
                            </span>
                        </td>
                        <td>{{ number_format((float) $calculation->required_hours, 2) }}</td>
                        <td>{{ number_format((float) $calculation->normal_hours, 2) }}</td>
                        <td>{{ number_format((float) $calculation->overtime_hours, 2) }}</td>
                        <td>{{ number_format((float) $calculation->absence_hours, 2) }}</td>
                        <td>{{ number_format((float) $calculation->leave_hours, 2) }}</td>
                        <td>{{ number_format((float) $calculation->mission_hours, 2) }}</td>
                        <td class="font-semibold text-slate-900">{{ number_format((float) $calculation->payable_hours, 2) }}</td>
                        <td class="max-w-md text-xs leading-6 text-slate-600">
                            @if($rule === 'hourly_actual_work_only')
                                ساعتی: فقط ساعت واقعی کارکرد مبناست؛ غیبت و اضافه‌کاری صفر است.
                            @else
                                ماهانه: شیفت و تقویم کاری، مرخصی و ماموریت تایید شده.
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="py-8 text-center text-slate-500">برای این دوره هنوز محاسبه کارکردی وجود ندارد.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $calculations->links() }}</div>
    </div>
</div>
