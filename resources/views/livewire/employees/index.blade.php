<div class="py-4 py-md-5">
    <div class="bg-white rounded-lg shadow-md p-3 p-md-4 space-y-4">
        <div class="d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-3">
            <h2 class="text-2xl font-bold mb-0">لیست پرسنل</h2>
            <a href="{{ route('employees.create') }}" class="erp-action-btn erp-action-edit text-center">پرسنل جدید</a>
        </div>

        @include('livewire.partials.flash')

        <form wire:submit.prevent class="erp-ui-filter-bar">
            <div class="row g-2 g-md-3 align-items-end">
                <label class="erp-filter-field col-12 col-md-6 col-lg-3">ID
                    <input wire:model.live.debounce.400ms="employee_id" inputmode="numeric" dir="ltr" placeholder="شناسه">
                </label>
                <label class="erp-filter-field col-12 col-md-6 col-lg-3">نام یا ایمیل
                    <input wire:model.live.debounce.400ms="name" placeholder="نام، نام خانوادگی، ایمیل">
                </label>
                <label class="erp-filter-field col-12 col-md-6 col-lg-3">کد ملی
                    <input wire:model.live.debounce.400ms="national_code" inputmode="numeric" dir="ltr" placeholder="کد ملی">
                </label>
                <label class="erp-filter-field col-12 col-md-6 col-lg-3">سمت
                    <input wire:model.live.debounce.400ms="position" placeholder="سمت">
                </label>
                <label class="erp-filter-field col-12 col-md-6 col-lg-3">تلفن
                    <input wire:model.live.debounce.400ms="phone" inputmode="tel" dir="ltr" placeholder="شماره تماس">
                </label>
                <label class="erp-filter-field col-12 col-md-6 col-lg-3">وضعیت
                    <select wire:model.live="status">
                        <option value="">همه</option>
                        <option value="active">فعال</option>
                        <option value="inactive">غیرفعال</option>
                        <option value="ended">پایان همکاری</option>
                    </select>
                </label>
                <label class="erp-filter-field col-12 col-md-6 col-lg-3">از تاریخ شروع
                    <input wire:model.live.debounce.500ms="start_date" inputmode="numeric" dir="ltr" placeholder="1403/01/01">
                </label>
                <div class="col-12 col-md-6 col-lg-3 d-grid">
                    <button type="button" wire:click="clearFilters" class="erp-action-btn">حذف فیلترها</button>
                </div>
            </div>
        </form>

        @foreach(array_filter($dateErrors ?? []) as $error)
            <div class="erp-filter-error">{{ $error }}</div>
        @endforeach
        <div wire:loading.delay class="text-sm text-slate-500">در حال به‌روزرسانی...</div>

        <div class="table-responsive overflow-x-auto">
            <table class="erp-ui-data-table w-full">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>نام</th>
                        <th>کد ملی</th>
                        <th>سمت</th>
                        <th>تلفن</th>
                        <th>تاریخ شروع</th>
                        <th>مدت همکاری</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($employees as $employee)
                    <tr wire:key="employee-{{ $employee->id }}" class="cursor-pointer hover:bg-slate-50" onclick="window.location='{{ route('salaries.employee-statement', $employee) }}'">
                        <td class="font-semibold">{{ $employee->id }}</td>
                        <td>
                            <span class="font-bold text-blue-700">{{ $employee->full_name }}</span>
                            <div class="text-xs text-gray-600 mt-1">{{ $employee->email ?: '-' }}</div>
                        </td>
                        <td>{{ $employee->national_code ?: '-' }}</td>
                        <td>
                            <div>{{ $employee->positionRecord?->title ?: $employee->position }}</div>
                            <div class="text-xs text-gray-600 mt-1">{{ $employee->organizationUnit?->title ?: '-' }}</div>
                        </td>
                        <td dir="ltr">{{ $employee->phone ?: '-' }}</td>
                        <td class="text-nowrap">{{ formatJalaliDateSafe($employee->hire_date ?: $employee->start_date) }}</td>
                        <td>{{ $employee->employment_duration }}</td>
                        <td>
                            <span class="px-2 py-1 rounded text-xs {{ $employee->status_color }}">{{ $employee->employment_status }}</span>
                        </td>
                        <td onclick="event.stopPropagation()">
                            <div class="d-grid d-sm-flex gap-2">
                                <a href="{{ route('employees.show', $employee) }}" class="erp-action-btn erp-action-detail text-center">جزئیات</a>
                                <a href="{{ route('employees.edit', $employee) }}" class="erp-action-btn erp-action-edit text-center">ویرایش پرونده</a>
                                <button type="button" wire:click="delete({{ $employee->id }})" wire:confirm="آیا از حذف این پرسنل مطمئن هستید؟" wire:loading.attr="disabled" wire:target="delete({{ $employee->id }})" class="erp-action-btn erp-action-delete">حذف</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-gray-500 py-6">هنوز پرسنلی اضافه نکرده‌اید.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $employees->links() }}</div>
    </div>
</div>
