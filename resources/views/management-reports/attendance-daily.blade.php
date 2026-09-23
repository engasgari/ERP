<x-erp.ui.page-shell title="گزارش ریز کارکرد روزانه">
    <x-erp.ui.panel>
        <x-erp.ui.page-header
            title="گزارش ریز کارکرد روزانه"
            :actions="[['label' => 'فهرست گزارش‌ها', 'url' => route('management-reports.index'), 'class' => 'erp-action-detail']]"
        />

        <p class="mb-4 text-sm text-slate-600">
            ریز محاسبات روزانه حضور و غیاب با بازهٔ ساعت تأخیر، تعجیل و غیبت (مشابه گزارش کارکرد نرم‌افزارهای ایرانی).
        </p>

        <form method="GET" action="{{ route('management-reports.attendance-daily') }}" class="mb-6 grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 md:grid-cols-3 lg:grid-cols-6">
            <div>
                <label class="mb-1 block text-xs text-slate-600">پرسنل</label>
                <select name="employee_id" class="w-full rounded border-slate-300 text-sm">
                    <option value="">همه</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((string) ($filters['employee_id'] ?? '') === (string) $employee->id)>
                            {{ $employee->full_name }} ({{ $employee->personnel_code }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs text-slate-600">دوره حقوق</label>
                <select name="payroll_period_id" class="w-full rounded border-slate-300 text-sm">
                    <option value="">همه</option>
                    @foreach($periods as $period)
                        <option value="{{ $period->id }}" @selected((string) ($filters['payroll_period_id'] ?? '') === (string) $period->id)>
                            {{ $period->persian_title }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs text-slate-600">سال</label>
                <input type="number" name="year" value="{{ $filters['year'] ?? '' }}" class="w-full rounded border-slate-300 text-sm" placeholder="1405">
            </div>
            <div>
                <label class="mb-1 block text-xs text-slate-600">ماه</label>
                <input type="number" name="month" min="1" max="12" value="{{ $filters['month'] ?? '' }}" class="w-full rounded border-slate-300 text-sm" placeholder="1">
            </div>
            <div>
                <label class="mb-1 block text-xs text-slate-600">از تاریخ (جلالی)</label>
                <input type="text" name="date_from" value="{{ request('date_from') }}" class="w-full rounded border-slate-300 text-sm" placeholder="1405/01/01" dir="ltr">
            </div>
            <div>
                <label class="mb-1 block text-xs text-slate-600">تا تاریخ (جلالی)</label>
                <input type="text" name="date_to" value="{{ request('date_to') }}" class="w-full rounded border-slate-300 text-sm" placeholder="1405/02/31" dir="ltr">
            </div>
            <div class="flex items-end gap-4 md:col-span-2">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="only_exceptions" value="1" @checked(!empty($filters['only_exceptions']))>
                    فقط استثناها (تأخیر/تعجیل/غیبت)
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="only_working_days" value="0">
                    <input type="checkbox" name="only_working_days" value="1" @checked(!empty($filters['only_working_days']))>
                    فقط روزهای کاری
                </label>
            </div>
            <div class="flex items-end gap-2 md:col-span-2">
                <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700">اعمال فیلتر</button>
                <a href="{{ route('management-reports.attendance-daily') }}" class="rounded-md bg-slate-200 px-4 py-2 text-sm text-slate-700 hover:bg-slate-300">پاک‌سازی</a>
            </div>
        </form>

        <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-5">
            <div class="rounded-lg bg-slate-50 p-3"><div class="text-xs text-slate-500">تعداد روز</div><div class="font-bold">{{ formatMoney($summary['days']) }}</div></div>
            <div class="rounded-lg bg-emerald-50 p-3"><div class="text-xs text-slate-500">کارکرد</div><div class="font-bold text-emerald-700">{{ formatMoney($summary['worked_hours']) }}</div></div>
            <div class="rounded-lg bg-amber-50 p-3"><div class="text-xs text-slate-500">تأخیر</div><div class="font-bold text-amber-700">{{ formatMoney($summary['delay_hours']) }}</div></div>
            <div class="rounded-lg bg-orange-50 p-3"><div class="text-xs text-slate-500">تعجیل</div><div class="font-bold text-orange-700">{{ formatMoney($summary['early_leave_hours']) }}</div></div>
            <div class="rounded-lg bg-rose-50 p-3"><div class="text-xs text-slate-500">غیبت</div><div class="font-bold text-rose-700">{{ formatMoney($summary['absence_hours']) }}</div></div>
        </div>

        <div class="overflow-x-auto">
            <table class="erp-compact-table w-full border-collapse border border-slate-300 text-xs md:text-sm">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="border border-slate-300 p-2">پرسنل</th>
                        <th class="border border-slate-300 p-2">تاریخ</th>
                        <th class="border border-slate-300 p-2">شیفت</th>
                        <th class="border border-slate-300 p-2">ورود</th>
                        <th class="border border-slate-300 p-2">خروج</th>
                        <th class="border border-slate-300 p-2">کارکرد</th>
                        <th class="border border-slate-300 p-2">تأخیر</th>
                        <th class="border border-slate-300 p-2">بازه تأخیر</th>
                        <th class="border border-slate-300 p-2">تعجیل</th>
                        <th class="border border-slate-300 p-2">بازه تعجیل</th>
                        <th class="border border-slate-300 p-2">غیبت</th>
                        <th class="border border-slate-300 p-2">بازه غیبت</th>
                        <th class="border border-slate-300 p-2">اضافه‌کار</th>
                        <th class="border border-slate-300 p-2">مرخصی/ماموریت</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr class="{{ $row['has_exception'] ? 'bg-rose-50/40' : '' }}">
                            <td class="border border-slate-300 p-2 whitespace-nowrap">
                                <div class="font-medium">{{ $row['employee_name'] }}</div>
                                <div class="text-[11px] text-slate-500">{{ $row['personnel_code'] }}</div>
                            </td>
                            <td class="border border-slate-300 p-2 whitespace-nowrap" dir="ltr">{{ $row['work_date_jalali'] }}</td>
                            <td class="border border-slate-300 p-2 whitespace-nowrap" dir="ltr">{{ $row['shift_start'] }}-{{ $row['shift_end'] }}</td>
                            <td class="border border-slate-300 p-2" dir="ltr">{{ $row['first_in'] }}</td>
                            <td class="border border-slate-300 p-2" dir="ltr">{{ $row['last_out'] }}</td>
                            <td class="border border-slate-300 p-2 font-medium">{{ formatMoney($row['worked_hours']) }}</td>
                            <td class="border border-slate-300 p-2 {{ $row['delay_hours'] > 0 ? 'text-amber-700 font-semibold' : '' }}">{{ formatMoney($row['delay_hours']) }}</td>
                            <td class="border border-slate-300 p-2 whitespace-nowrap" dir="ltr">{{ $row['delay_range'] }}</td>
                            <td class="border border-slate-300 p-2 {{ $row['early_leave_hours'] > 0 ? 'text-orange-700 font-semibold' : '' }}">{{ formatMoney($row['early_leave_hours']) }}</td>
                            <td class="border border-slate-300 p-2 whitespace-nowrap" dir="ltr">{{ $row['early_leave_range'] }}</td>
                            <td class="border border-slate-300 p-2 {{ $row['absence_hours'] > 0 ? 'text-rose-700 font-semibold' : '' }}">{{ formatMoney($row['absence_hours']) }}</td>
                            <td class="border border-slate-300 p-2 whitespace-nowrap" dir="ltr">{{ $row['absence_range'] }}</td>
                            <td class="border border-slate-300 p-2">{{ formatMoney($row['overtime_hours']) }}</td>
                            <td class="border border-slate-300 p-2">{{ formatMoney($row['leave_hours']) }} / {{ formatMoney($row['mission_hours']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="14" class="border border-slate-300 px-3 py-8 text-center text-slate-500">
                                رکوردی یافت نشد. ابتدا محاسبه کارکرد دوره را انجام دهید یا فیلتر را تغییر دهید.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-erp.ui.panel>
</x-erp.ui.page-shell>
