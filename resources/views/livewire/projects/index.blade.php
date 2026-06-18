<div class="py-4 py-md-5">
    <div class="rounded-lg bg-white p-3 p-md-4 shadow space-y-4">
        <div class="d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-3">
            <div>
                <h2 class="text-lg font-black text-slate-800 mb-1">لیست پروژه‌ها</h2>
                <p class="text-sm text-slate-500 mb-0">پروژه‌ها مرکز هزینه تولید، کارکرد، انبار و مالی هستند.</p>
            </div>
            <a href="{{ route('projects.create') }}" class="erp-action-btn erp-action-edit text-center">پروژه جدید</a>
        </div>

        @include('livewire.partials.flash')

        <form wire:submit.prevent class="erp-ui-filter-bar">
            <div class="row g-2 g-md-3 align-items-end">
                <label class="erp-filter-field col-12 col-md-6 col-lg-3">نام پروژه
                    <input wire:model.live.debounce.400ms="name" placeholder="نام یا توضیح">
                </label>
                <label class="erp-filter-field col-12 col-md-6 col-lg-3">وضعیت
                    <select wire:model.live="status">
                        <option value="">همه</option>
                        @foreach($statuses as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="erp-filter-field col-12 col-md-6 col-lg-2">از تاریخ شروع
                    <input wire:model.live.debounce.500ms="start_date" inputmode="numeric" dir="ltr" placeholder="1403/01/01">
                </label>
                <label class="erp-filter-field col-12 col-md-6 col-lg-2">تا تاریخ پایان
                    <input wire:model.live.debounce.500ms="end_date" inputmode="numeric" dir="ltr" placeholder="1403/12/29">
                </label>
                <div class="col-12 col-lg-2 d-grid">
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
                        <th>پروژه</th>
                        <th>کارفرما</th>
                        <th>مدیر</th>
                        <th>وضعیت</th>
                        <th>بودجه</th>
                        <th>سود/زیان فعلی</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($projects as $project)
                    @php($profit = $project->gross_profit)
                    <tr wire:key="project-{{ $project->id }}">
                        <td>
                            <input class="form-control form-control-sm" value="{{ $project->name }}" wire:change="updateField({{ $project->id }}, 'name', $event.target.value)">
                            <div class="mt-1 text-xs text-slate-500">{{ $project->project_number ?: '-' }}</div>
                        </td>
                        <td>{{ $project->party?->name ?: '-' }}</td>
                        <td>{{ $project->manager?->name ?: '-' }}</td>
                        <td>
                            <select class="form-select form-select-sm" wire:change="updateField({{ $project->id }}, 'status', $event.target.value)">
                                @foreach($statuses as $value => $label)
                                    <option value="{{ $value }}" @selected($project->status === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input type="number" min="0" step="0.01" class="form-control form-control-sm" dir="ltr" value="{{ $project->budget }}" wire:change="updateField({{ $project->id }}, 'budget', $event.target.value)">
                        </td>
                        <td class="whitespace-nowrap">
                            <span class="font-bold {{ $profit >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ number_format($profit) }}</span>
                        </td>
                        <td>
                            <div class="d-grid d-sm-flex gap-2">
                                <a href="{{ route('projects.show', $project) }}" class="erp-action-btn erp-action-detail text-center">جزئیات</a>
                                <a href="{{ route('projects.costing', $project) }}" class="erp-action-btn erp-action-detail text-center">بهای تمام‌شده</a>
                                <a href="{{ route('production-orders.create', ['project_id' => $project->id]) }}" class="erp-action-btn erp-action-edit text-center">سفارش تولید</a>
                                <a href="{{ route('projects.edit', $project) }}" class="erp-action-btn erp-action-edit text-center">ویرایش کامل</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-6 text-center text-slate-500">هنوز پروژه‌ای ثبت نشده است.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $projects->links() }}</div>
    </div>
</div>
