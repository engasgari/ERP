<x-erp.ui.page-shell title="گزارش لیست پرسنل">
    <x-erp.ui.panel>
        <x-erp.ui.page-header title="لیست پرسنل" />

        <x-erp.ui.filter-bar method="GET">
            <div class="row g-2">
                <label class="erp-filter-field col-12 col-md-6">جستجو
                    <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="نام، کد پرسنلی یا کد ملی">
                </label>
                <label class="erp-filter-field col-12 col-md-3">وضعیت
                    <select name="status">
                        <option value="">همه</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>فعال</option>
                        <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>غیرفعال</option>
                        <option value="terminated" @selected(($filters['status'] ?? '') === 'terminated')>پایان همکاری</option>
                    </select>
                </label>
                <div class="col-12 col-md-3 d-grid"><button class="erp-action-btn">اعمال فیلتر</button></div>
            </div>
        </x-erp.ui.filter-bar>

        <x-erp.ui.data-table :headers="['کد', 'نام', 'کد ملی', 'واحد', 'پست', 'تاریخ استخدام', 'وضعیت']" colspan="7">
            @forelse($rows as $employee)
                <tr>
                    <td>{{ $employee->personnel_code ?: $employee->id }}</td>
                    <td>{{ $employee->full_name }}</td>
                    <td>{{ $employee->national_code ?: '-' }}</td>
                    <td>{{ $employee->department ?: '-' }}</td>
                    <td>{{ $employee->position ?: '-' }}</td>
                    <td>{{ formatJalaliDateSafe($employee->hire_date ?: $employee->start_date) }}</td>
                    <td>{{ $employee->employment_status }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-6 text-center text-slate-500">رکوردی یافت نشد.</td></tr>
            @endforelse
        </x-erp.ui.data-table>

        {{ $rows->links() }}
    </x-erp.ui.panel>
</x-erp.ui.page-shell>
