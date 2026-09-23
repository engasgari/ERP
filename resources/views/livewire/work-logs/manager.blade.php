<div>
    @include('livewire.partials.flash')

    <style>
        .worklog-page .worklog-toolbar {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            flex-wrap: wrap;
            margin-bottom: 0.75rem;
            padding: 0.45rem 0.6rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.4rem;
        }
        .worklog-page .worklog-tool-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
            min-height: 2rem;
            padding: 0.3rem 0.65rem;
            border: 1px solid #cbd5e1;
            border-radius: 0.375rem;
            background: #fff;
            color: #334155;
            font-size: 0.78rem;
            font-weight: 600;
            line-height: 1;
            cursor: pointer;
        }
        .worklog-page .worklog-tool-btn svg {
            display: block;
            width: 1rem;
            height: 1rem;
            flex-shrink: 0;
            stroke: currentColor;
            fill: none;
        }
        .worklog-page .worklog-tool-btn:hover:not(:disabled) {
            background: #e2e8f0;
            border-color: #94a3b8;
            color: #0f172a;
        }
        .worklog-page .worklog-tool-btn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }
        .worklog-page .worklog-tool-btn--danger {
            color: #b91c1c;
            border-color: #fecaca;
            background: #fff;
        }
        .worklog-page .worklog-tool-btn--danger:hover:not(:disabled) {
            background: #fef2f2;
            border-color: #fca5a5;
            color: #991b1b;
        }
        .worklog-page .worklog-tool-btn--project {
            color: #0f766e;
            border-color: #99f6e4;
            background: #fff;
        }
        .worklog-page .worklog-tool-btn--project:hover:not(:disabled) {
            background: #f0fdfa;
            border-color: #5eead4;
            color: #115e59;
        }
        .worklog-page .worklog-toolbar-count {
            margin-inline-start: 0.15rem;
            font-size: 0.78rem;
            color: #64748b;
        }
        .worklog-page .worklog-check {
            width: 13px;
            height: 13px;
            margin: 0;
            border-radius: 0 !important;
            accent-color: #2563eb;
            vertical-align: middle;
        }
        .worklog-page .worklog-project-select {
            width: 100%;
            min-width: 11rem;
            height: 1.35rem;
            padding: 0;
            font-size: 0.55rem;
            line-height: 1.1;
            border: 0 !important;
            border-radius: 0;
            background: transparent;
            box-shadow: none !important;
            outline: none;
            cursor: pointer;
        }
        .worklog-page .worklog-project-select.is-empty {
            color: #1d4ed8;
            background: #eff6ff;
            padding: 0 0.2rem;
        }
        .worklog-page .worklog-project-select:focus {
            background: #dbeafe;
        }
        .worklog-page .worklog-project-select:not(.is-empty) {
            background: #f8fbff;
        }
        .worklog-page .worklog-inline {
            width: 100%;
            height: 1.35rem;
            padding: 0 0.15rem;
            font-size: 0.55rem;
            line-height: 1.1;
            border: 0 !important;
            border-radius: 0;
            background: transparent;
            box-shadow: none !important;
            outline: none;
        }
        .worklog-page .worklog-inline:focus {
            background: #eff6ff;
        }
        .worklog-page .worklog-inline[type="time"] {
            min-width: 5.5rem;
            padding-inline-end: 0.1rem;
        }
        .worklog-page .worklog-inline[type="time"]::-webkit-calendar-picker-indicator {
            cursor: pointer;
            opacity: 0.65;
            padding: 0;
            margin: 0;
        }
        .worklog-page .worklog-inline.erp-jalali-date-input,
        .worklog-page .worklog-inline[data-jalali-datepicker] {
            direction: ltr;
            text-align: left;
            min-width: 6.75rem;
        }
        .worklog-page .worklog-code {
            min-width: 5.5rem;
            text-align: right !important;
            direction: rtl;
            font-variant-numeric: tabular-nums;
            font-weight: 600;
        }
        .worklog-page table.erp-ui-data-table {
            border-spacing: 0 !important;
            border-collapse: collapse !important;
            font-size: 0.72rem !important;
            border: 1px solid #cbd5e1 !important;
        }
        .worklog-page table.erp-ui-data-table th,
        .worklog-page table.erp-ui-data-table td {
            padding: 0.15rem 0.35rem !important;
            vertical-align: middle !important;
            white-space: nowrap;
            border: 1px solid #cbd5e1 !important;
        }
        .worklog-page table.erp-ui-data-table thead th {
            background: #f1f5f9 !important;
        }
        .worklog-page table.erp-ui-data-table tbody tr.worklog-incomplete {
            background: #fef2f2 !important;
            color: #991b1b;
        }
        .worklog-page table.erp-ui-data-table tbody tr.worklog-incomplete:hover {
            background: #fee2e2 !important;
        }
        .worklog-page .worklog-name {
            max-width: 9rem;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .worklog-page .worklog-ltr {
            direction: ltr;
            unicode-bidi: isolate;
            font-variant-numeric: tabular-nums;
            text-align: right;
        }
        .worklog-page col.col-check { width: 28px; }
        .worklog-page col.col-code { width: 88px; }
        .worklog-page col.col-name { width: 120px; }
        .worklog-page col.col-project { width: 180px; }
        .worklog-page col.col-date { width: 108px; }
        .worklog-page col.col-time { width: 78px; }
        .worklog-page col.col-hours { width: 58px; }
        .worklog-page col.col-actions { width: 64px; }
        .worklog-page .worklog-project-modal-list {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            max-height: 22rem;
            overflow-y: auto;
            padding: 0.15rem;
        }
        .worklog-page .worklog-project-modal-item {
            width: 100%;
            text-align: right;
            padding: 0.55rem 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.4rem;
            background: #fff;
            font-size: 0.85rem;
            color: #0f172a;
            cursor: pointer;
        }
        .worklog-page .worklog-project-modal-item:hover {
            background: #f0fdfa;
            border-color: #99f6e4;
            color: #0f766e;
        }
        .worklog-delete-modal.erp-ui-modal-panel {
            width: 17.5rem !important;
            max-width: 17.5rem !important;
            min-height: 17.5rem;
            border-radius: 0.75rem !important;
            border: 1px solid #e2e8f0 !important;
            background: #fff !important;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .worklog-delete-modal .erp-modal-header {
            background: #f8fafc;
            color: #0f172a;
            border-bottom: 1px solid #e2e8f0;
            padding: 0.85rem 1rem;
        }
        .worklog-delete-modal .erp-modal-header h3 {
            color: #0f172a;
            font-size: 0.95rem;
            margin: 0;
        }
        .worklog-delete-modal .erp-modal-close {
            color: #64748b;
            background: transparent;
            border: 0;
            font-size: 1.35rem;
            line-height: 1;
            opacity: 0.9;
        }
        .worklog-delete-modal .erp-modal-close:hover {
            color: #0f172a;
            opacity: 1;
        }
        .worklog-delete-modal .erp-modal-body {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 1.25rem 1rem;
        }
        .worklog-delete-modal .worklog-delete-message {
            margin: 0;
            color: #dc2626;
            font-size: 0.9rem;
            font-weight: 600;
            line-height: 1.6;
        }
        .worklog-delete-modal .erp-modal-actions {
            justify-content: center;
            border-top: 1px solid #e2e8f0;
            background: #fff;
            padding: 0.75rem;
            gap: 0.45rem;
        }
        .worklog-delete-modal .worklog-delete-cancel {
            border: 1px solid #cbd5e1 !important;
            background: #fff !important;
            color: #334155 !important;
        }
        .worklog-delete-modal .worklog-delete-submit {
            border: 1px solid #b91c1c !important;
            background: #dc2626 !important;
            color: #fff !important;
        }
        .worklog-delete-modal .worklog-delete-submit:hover {
            background: #b91c1c !important;
        }
    </style>

    <x-erp.ui.list-page
        title="لیست کارکرد"
        description="ویرایش مستقیم پروژه، تاریخ، ورود، خروج و ساعت در همین صفحه."
        route="work-logs.index"
        :actions="[
            ['label' => 'ورود اکسل', 'url' => route('worklog.import.view'), 'class' => 'erp-action-detail'],
            ['label' => 'خروجی', 'url' => route('worklog.export'), 'class' => 'erp-action-detail'],
        ]"
        panel-class="worklog-page"
    >
        <x-slot name="filters">
            <x-erp.ui.filter-bar wire:submit.prevent>
                <div class="erp-filter-row erp-filter-row-5">
                    <label class="erp-filter-field">پرسنل
                        <select wire:model.live="employeeId">
                            <option value="">همه پرسنل</option>
                            @foreach($employees as $employeeItem)
                                <option value="{{ $employeeItem->id }}">
                                    {{ $employeeItem->personnel_code ?: $employeeItem->employee_code }} — {{ $employeeItem->full_name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label class="erp-filter-field">پروژه
                        <select wire:model.live="project">
                            <option value="">همه پروژه‌ها</option>
                            <option value="none">بدون پروژه</option>
                            @foreach($projects as $projectItem)
                                <option value="{{ $projectItem->id }}">{{ $projectItem->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="erp-filter-field">وضعیت
                        <select wire:model.live="statusFilter">
                            <option value="">همه</option>
                            <option value="incomplete">تردد ناقص</option>
                            <option value="complete">کامل</option>
                        </select>
                    </label>
                    <label class="erp-filter-field">از تاریخ
                        <x-erp.ui.jalali-date-input wire:model.live.debounce.500ms="startDateFa" placeholder="1403/01/01" class="w-full" />
                    </label>
                    <label class="erp-filter-field">تا تاریخ
                        <x-erp.ui.jalali-date-input wire:model.live.debounce.500ms="endDateFa" placeholder="1403/12/29" class="w-full" />
                    </label>
                </div>
                <div class="erp-filter-actions mt-2">
                    <button type="button" wire:click="clearFilters" class="erp-action-btn">حذف فیلترها</button>
                </div>
            </x-erp.ui.filter-bar>
        </x-slot>

        <div class="worklog-toolbar">
            <button
                type="button"
                class="worklog-tool-btn worklog-tool-btn--danger"
                title="حذف انتخاب‌شده‌ها"
                wire:click="openDeleteConfirm"
                @disabled(count($selected) === 0)
            >
                <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    <line x1="10" y1="11" x2="10" y2="17"></line>
                    <line x1="14" y1="11" x2="14" y2="17"></line>
                </svg>
                <span>حذف</span>
            </button>
            <button
                type="button"
                class="worklog-tool-btn worklog-tool-btn--project"
                title="تخصیص پروژه"
                wire:click="openBulkProjectModal"
                @disabled(count($selected) === 0)
            >
                <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                </svg>
                <span>پروژه</span>
            </button>
            @if(count($selected) > 0)
                <span class="worklog-toolbar-count">{{ toPersianDigits(count($selected)) }} مورد</span>
            @endif
        </div>

        <div class="erp-table-wrap table-responsive overflow-x-auto">
            <table class="erp-ui-data-table w-full">
                <colgroup>
                    <col class="col-check">
                    <col class="col-code">
                    <col class="col-name">
                    <col class="col-project">
                    <col class="col-date">
                    <col class="col-time">
                    <col class="col-time">
                    <col class="col-hours">
                    <col class="col-actions">
                </colgroup>
                <thead>
                <tr>
                    <th class="!text-center">
                        <input type="checkbox" class="worklog-check" wire:model.live="selectAll" title="انتخاب همه">
                    </th>
                    <th class="!text-right">کد پرسنلی</th>
                    <th>نام</th>
                    <th>پروژه</th>
                    <th>تاریخ</th>
                    <th>ورود</th>
                    <th>خروج</th>
                    <th>ساعت</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($workLogs as $workLog)
                    @php
                        $codeRaw = $workLog->employee?->personnel_code ?: $workLog->employee?->employee_code;
                        $dateFa = gregorianToJalaliDate($workLog->work_date);
                        $start = $workLog->start_time ? substr((string) $workLog->start_time, 0, 5) : '';
                        $end = $workLog->end_time ? substr((string) $workLog->end_time, 0, 5) : '';
                        $hours = rtrim(rtrim(number_format((float) $workLog->hours, 2, '.', ''), '0'), '.');
                    @endphp
                    <tr wire:key="work-log-{{ $workLog->id }}" @class(['worklog-incomplete' => $workLog->is_incomplete])>
                        <td class="!text-center">
                            <input type="checkbox" class="worklog-check" wire:model.live="selected" value="{{ $workLog->id }}">
                        </td>
                        <td class="worklog-code">
                            {{ $codeRaw ? toPersianDigits($codeRaw) : '—' }}
                        </td>
                        <td class="worklog-name" title="{{ $workLog->employee?->full_name }}">
                            {{ $workLog->employee?->full_name }}
                            @if($workLog->is_incomplete)
                                <span class="text-[10px] font-bold text-red-600">ناقص</span>
                            @endif
                        </td>
                        <td>
                            <select
                                wire:change="updateField({{ $workLog->id }}, 'project_id', $event.target.value)"
                                class="worklog-project-select {{ $workLog->project_id ? '' : 'is-empty' }}"
                            >
                                <option value="" @selected($workLog->project_id === null)>— انتخاب پروژه —</option>
                                @foreach($projects as $projectItem)
                                    <option value="{{ $projectItem->id }}" @selected($workLog->project_id == $projectItem->id)>
                                        {{ $projectItem->name }}
                                    </option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input
                                type="text"
                                class="worklog-inline worklog-ltr erp-jalali-date-input"
                                value="{{ $dateFa }}"
                                dir="ltr"
                                inputmode="numeric"
                                autocomplete="off"
                                data-jalali-datepicker
                                placeholder="1403/01/01"
                                title="سال/ماه/روز — با کلیک تقویم شمسی باز می‌شود"
                                wire:change="updateField({{ $workLog->id }}, 'work_date', $event.target.value)"
                            >
                        </td>
                        <td>
                            <input
                                type="time"
                                class="worklog-inline worklog-ltr"
                                value="{{ $start }}"
                                dir="ltr"
                                step="60"
                                wire:change="updateField({{ $workLog->id }}, 'start_time', $event.target.value)"
                            >
                        </td>
                        <td>
                            <input
                                type="time"
                                class="worklog-inline worklog-ltr"
                                value="{{ $end }}"
                                dir="ltr"
                                step="60"
                                wire:change="updateField({{ $workLog->id }}, 'end_time', $event.target.value)"
                            >
                        </td>
                        <td>
                            <input
                                type="text"
                                class="worklog-inline worklog-ltr"
                                value="{{ toPersianDigits($hours) }}"
                                dir="ltr"
                                inputmode="decimal"
                                placeholder="۰"
                                wire:change="updateField({{ $workLog->id }}, 'hours', $event.target.value)"
                            >
                        </td>
                        <td>
                            <x-erp.ui.row-actions>
                                <x-erp.ui.row-action
                                    icon="delete"
                                    label="حذف"
                                    tone="danger"
                                    wire:click="openDeleteConfirm({{ $workLog->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="openDeleteConfirm({{ $workLog->id }})"
                                />
                            </x-erp.ui.row-actions>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="py-6 text-center text-slate-500">کارکردی پیدا نشد.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($workLogs->count() > 0)
            <div class="mt-4">{{ $workLogs->links() }}</div>
        @endif
    </x-erp.ui.list-page>

    @if($showDeleteConfirmModal)
        <div class="erp-ui-modal-backdrop" wire:click="closeDeleteConfirm">
            <div class="erp-ui-modal-panel worklog-delete-modal" wire:click.stop>
                <div class="erp-modal-header">
                    <h3>تایید حذف</h3>
                    <button type="button" class="erp-modal-close" wire:click="closeDeleteConfirm">×</button>
                </div>
                <div class="erp-modal-body">
                    <p class="worklog-delete-message">{{ $deleteConfirmMessage }}</p>
                </div>
                <div class="erp-modal-actions">
                    <button type="button" class="erp-action-btn worklog-delete-cancel" wire:click="closeDeleteConfirm">انصراف</button>
                    <button
                        type="button"
                        class="erp-action-btn worklog-delete-submit"
                        wire:click="confirmDelete"
                        wire:loading.attr="disabled"
                        wire:target="confirmDelete"
                    >
                        حذف شود
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($showBulkProjectModal)
        <div class="erp-ui-modal-backdrop" wire:click="closeBulkProjectModal">
            <div class="erp-ui-modal-panel worklog-page" wire:click.stop style="max-width: 28rem;">
                <div class="erp-modal-header">
                    <h3>انتخاب پروژه</h3>
                    <button type="button" class="erp-modal-close" wire:click="closeBulkProjectModal">×</button>
                </div>
                <div class="erp-modal-body">
                    <p class="mb-3 text-sm text-slate-500">
                        تخصیص به {{ toPersianDigits(count($selected)) }} ردیف انتخاب‌شده
                    </p>
                    <div class="worklog-project-modal-list">
                        @forelse($projects as $projectItem)
                            <button
                                type="button"
                                class="worklog-project-modal-item"
                                wire:click="bulkAssignProject({{ $projectItem->id }})"
                                wire:loading.attr="disabled"
                                wire:target="bulkAssignProject"
                            >
                                {{ $projectItem->name }}
                            </button>
                        @empty
                            <div class="py-4 text-center text-sm text-slate-500">پروژه‌ای تعریف نشده است.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
