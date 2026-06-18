<div class="bg-white rounded-lg shadow-md p-3 p-md-4 space-y-4">
    <div class="d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-3">
        <h3 class="text-lg font-bold mb-0">لیست شیفت‌ها</h3>
        <a href="{{ route('work-shifts.create') }}" class="erp-action-btn erp-action-edit text-center">ثبت شیفت</a>
    </div>

    @include('livewire.partials.flash')

    <form wire:submit.prevent class="erp-ui-filter-bar">
        <div class="row g-2 g-md-3 align-items-end">
            <label class="erp-filter-field col-12 col-md-6 col-lg-7">جستجو
                <input wire:model.live.debounce.400ms="search" placeholder="کد یا نام شیفت">
            </label>
            <label class="erp-filter-field col-12 col-md-6 col-lg-3">وضعیت
                <select wire:model.live="is_active">
                    <option value="">همه</option>
                    <option value="1">فعال</option>
                    <option value="0">غیرفعال</option>
                </select>
            </label>
            <div class="col-12 col-lg-2 d-grid">
                <button type="button" wire:click="clearFilters" class="erp-action-btn">حذف فیلترها</button>
            </div>
        </div>
    </form>

    <div wire:loading.delay class="text-sm text-slate-500">در حال به‌روزرسانی...</div>

    <div class="table-responsive overflow-x-auto">
        <table class="erp-ui-data-table min-w-full">
            <thead>
                <tr>
                    <th>کد</th>
                    <th>نام شیفت</th>
                    <th>شروع</th>
                    <th>پایان</th>
                    <th>استراحت</th>
                    <th>اضافه‌کاری</th>
                    <th>وضعیت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
            @forelse($shifts as $shift)
                <tr wire:key="shift-{{ $shift->id }}">
                    <td class="text-nowrap">{{ $shift->code }}</td>
                    <td>
                        <input class="form-control form-control-sm" value="{{ $shift->name }}" wire:change="updateField({{ $shift->id }}, 'name', $event.target.value)">
                    </td>
                    <td class="text-nowrap">{{ $shift->start_time }}</td>
                    <td class="text-nowrap">{{ $shift->end_time }}</td>
                    <td class="text-nowrap">{{ $shift->break_minutes }} دقیقه</td>
                    <td class="text-nowrap">{{ $shift->overtime_multiplier }}</td>
                    <td>
                        <select class="form-select form-select-sm" wire:change="updateField({{ $shift->id }}, 'is_active', $event.target.value)">
                            <option value="1" @selected($shift->is_active)>فعال</option>
                            <option value="0" @selected(! $shift->is_active)>غیرفعال</option>
                        </select>
                    </td>
                    <td>
                        <div class="d-grid d-sm-flex gap-2">
                            <a class="erp-action-btn erp-action-edit text-center" href="{{ route('work-shifts.edit', $shift) }}">ویرایش کامل</a>
                            <button type="button" wire:click="delete({{ $shift->id }})" wire:confirm="شیفت کاری حذف شود؟" wire:loading.attr="disabled" wire:target="delete({{ $shift->id }})" class="erp-action-btn erp-action-delete">حذف</button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-slate-500 py-6">رکوردی ثبت نشده است.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $shifts->links() }}</div>
</div>
