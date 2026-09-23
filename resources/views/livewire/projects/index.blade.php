<x-erp.ui.list-page
    title="لیست پروژه‌ها"
    description="پروژه‌ها مرکز هزینه تولید، کارکرد، انبار و مالی هستند."
    route="projects.index"
    :actions="[
        ['label' => 'پروژه جدید', 'url' => route('projects.create'), 'class' => 'erp-action-edit'],
    ]"
>
    <x-slot name="filters">
        <x-erp.ui.filter-bar wire:submit.prevent>
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
        </x-erp.ui.filter-bar>

        @foreach(array_filter($dateErrors ?? []) as $error)
            <div class="erp-filter-error">{{ $error }}</div>
        @endforeach
    </x-slot>

    <x-erp.ui.data-table
        :headers="['پروژه', 'کارفرما', 'مدیر', 'وضعیت', 'بودجه', 'سود/زیان فعلی', 'عملیات']"
        empty-message="هنوز پروژه‌ای ثبت نشده است."
        :colspan="7"
    >
        @foreach($projects as $project)
            @php($profit = $project->gross_profit)
            <tr wire:key="project-{{ $project->id }}">
                <td>
                    <span class="font-bold text-slate-800">{{ $project->name }}</span>
                    <div class="mt-1 text-xs text-slate-500">{{ $project->project_number ?: '-' }}</div>
                </td>
                <td>{{ $project->party?->name ?: '-' }}</td>
                <td>{{ $project->manager?->name ?: '-' }}</td>
                <td>{{ $project->status_label }}</td>
                <td>{{ $project->budget !== null ? formatMoney($project->budget) : '-' }}</td>
                <td class="whitespace-nowrap">
                    <span class="font-bold {{ $profit >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ formatMoney($profit) }}</span>
                </td>
                <td>
                    <x-erp.ui.action-menu :actions="[
                        ['label' => 'جزئیات', 'url' => route('projects.show', $project), 'class' => 'erp-action-detail'],
                        ['label' => 'بهای تمام‌شده', 'url' => route('projects.costing', $project), 'class' => 'erp-action-detail'],
                        ['label' => 'سفارش تولید', 'url' => route('production-orders.create', ['project_id' => $project->id]), 'icon' => 'production', 'class' => 'erp-action-edit'],
                        ['label' => 'ویرایش کامل', 'url' => route('projects.edit', $project), 'class' => 'erp-action-edit'],
                    ]" />
                </td>
            </tr>
        @endforeach
    </x-erp.ui.data-table>

    <div>{{ $projects->links() }}</div>
</x-erp.ui.list-page>
