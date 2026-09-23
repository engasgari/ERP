@php
    $statusLabels = ['confirmed' => 'تایید شده', 'draft' => 'موقت'];
@endphp
<x-erp.ui.list-page
    title="فهرست رسید و حواله انبار"
    description="مدیریت رسید، حواله خروج، حواله مصرف و انتقال بین انبار."
    route="inventory-documents.index"
    :actions="[
        ['label' => 'رسید انبار', 'url' => route('inventory-documents.create', ['type' => 'receipt']), 'class' => 'erp-action-edit'],
        ['label' => 'حواله خروج', 'url' => route('inventory-documents.create', ['type' => 'issue']), 'class' => 'erp-action-edit'],
        ['label' => 'حواله مصرف', 'url' => route('inventory-documents.create', ['type' => 'consumption']), 'class' => 'erp-action-edit'],
        ['label' => 'انتقال بین انبار', 'url' => route('inventory-documents.create', ['type' => 'transfer']), 'class' => 'erp-action-edit'],
    ]"
>
    <x-slot name="filters">
        <x-erp.ui.filter-bar wire:submit.prevent>
            <div class="erp-filter-row inventory-documents-filter-row">
                <label class="erp-filter-field">جستجو
                    <input wire:model.live.debounce.400ms="search" placeholder="شماره سند، توضیح، نام کالا">
                </label>
                <label class="erp-filter-field">نوع سند
                    <select wire:model.live="type">
                        <option value="">همه</option>
                        @foreach($documentTypeLabels as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="erp-filter-field">منبع
                    <select wire:model.live="entry_mode">
                        <option value="">همه</option>
                        <option value="manual">دستی</option>
                        <option value="automatic">اتوماتیک</option>
                    </select>
                </label>
                <label class="erp-filter-field">وضعیت
                    <select wire:model.live="status">
                        <option value="">همه</option>
                        <option value="draft">موقت</option>
                        <option value="confirmed">تایید شده</option>
                    </select>
                </label>
                <label class="erp-filter-field">انبار
                    <select wire:model.live="warehouse_id">
                        <option value="">همه</option>
                        @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="erp-filter-actions">
                    <button type="button" wire:click="clearFilters" class="erp-action-btn">حذف فیلترها</button>
                </div>
            </div>
        </x-erp.ui.filter-bar>
    </x-slot>

    <x-erp.ui.data-table
        :headers="['شماره سند', 'تاریخ', 'نوع', 'انبار', 'منبع', 'اقلام', 'وضعیت', 'عملیات']"
        empty-message="سند انباری یافت نشد."
        :colspan="8"
    >
        @foreach($documents as $document)
            @php
                $statusLabel = $statusLabels[$document->status] ?? $document->status;
                $statusTone = $document->status === 'confirmed' ? 'success' : 'warning';
            @endphp
            <tr wire:key="inventory-document-{{ $document->id }}">
                <td class="text-nowrap font-semibold">{{ $document->number }}</td>
                <td class="text-nowrap">{{ gregorianToJalaliDate($document->document_date) }}</td>
                <td>{{ $documentTypeLabels[$document->type] ?? $document->type }}</td>
                <td>
                    <div>{{ $document->warehouse?->name ?: '-' }}</div>
                    @if($document->targetWarehouse)
                        <div class="text-xs text-slate-500">به: {{ $document->targetWarehouse->name }}</div>
                    @endif
                </td>
                <td class="text-[0.6875rem] leading-snug text-slate-600">{{ $this->sourceSummary($document) }}</td>
                <td>
                    @if($document->is_initial_stock)
                        {{ formatQuantity((float) $document->lines->sum('quantity')) }}
                    @else
                        {{ $document->lines->count() }}
                    @endif
                </td>
                <td>
                    <x-erp.ui.status-badge :label="$statusLabel" :tone="$statusTone" />
                </td>
                <td>
                    <x-erp.ui.row-actions>
                        <x-erp.ui.row-action icon="view" label="جزئیات" wire:click="show({{ $document->id }})" />
                        <x-erp.ui.row-action icon="expand" label="نمایش کامل" wire:click="showFull({{ $document->id }})" />
                        <x-erp.ui.row-action icon="edit" label="ویرایش" :href="route('inventory-documents.edit', $document)" />
                        <x-erp.ui.row-action
                            icon="delete"
                            label="حذف"
                            tone="danger"
                            wire:click="delete({{ $document->id }})"
                            wire:confirm="آیا از حذف این سند انبار مطمئن هستید؟"
                            wire:loading.attr="disabled"
                            wire:target="delete({{ $document->id }})"
                        />
                    </x-erp.ui.row-actions>
                </td>
            </tr>
        @endforeach
    </x-erp.ui.data-table>

    <div>{{ $documents->links() }}</div>

    @if($showingDocument)
        @php
            $modalStatusLabel = $statusLabels[$showingDocument->status] ?? $showingDocument->status;
            $modalActions = [];

            if ($showMode === 'details') {
                $modalActions[] = [
                    'label' => 'نمایش کامل',
                    'type' => 'button',
                    'class' => 'erp-action-detail',
                    'attrs' => ['wire:click' => 'showFull(' . $showingDocument->id . ')'],
                ];
            }

            $modalActions[] = [
                'label' => 'ویرایش',
                'url' => route('inventory-documents.edit', $showingDocument),
                'class' => 'erp-action-edit',
            ];
        @endphp

        <x-erp.ui.details-modal
            :title="'سند انبار ' . $showingDocument->number"
            :actions="$modalActions"
        >
            <x-slot name="close">
                <button type="button" class="erp-modal-close" wire:click="closeModal">×</button>
            </x-slot>

            <div class="erp-modal-grid">
                <div class="erp-modal-field">شماره سند<div class="erp-modal-value">{{ $showingDocument->number }}</div></div>
                <div class="erp-modal-field">تاریخ<div class="erp-modal-value">{{ gregorianToJalaliDate($showingDocument->document_date) }}</div></div>
                <div class="erp-modal-field">نوع سند<div class="erp-modal-value">{{ $documentTypeLabels[$showingDocument->type] ?? $showingDocument->type }}</div></div>
                <div class="erp-modal-field">انبار<div class="erp-modal-value">{{ $showingDocument->warehouse?->name ?: '-' }}</div></div>
                <div class="erp-modal-field">انبار مقصد<div class="erp-modal-value">{{ $showingDocument->targetWarehouse?->name ?: '-' }}</div></div>
                <div class="erp-modal-field">پروژه<div class="erp-modal-value">{{ $showingDocument->project?->name ?: '-' }}</div></div>
                <div class="erp-modal-field">وضعیت<div class="erp-modal-value">{{ $modalStatusLabel }}</div></div>
                <div class="erp-modal-field">منبع<div class="erp-modal-value text-[0.75rem] leading-snug">{{ $this->sourceSummary($showingDocument) }}</div></div>
                <div class="erp-modal-field">سند حسابداری<div class="erp-modal-value">{{ $showingDocument->accountingDocument?->number ?: '-' }}</div></div>
                <div class="erp-modal-field md:col-span-2">توضیحات<div class="erp-modal-value">{{ $showingDocument->description ?: '-' }}</div></div>
            </div>

            @if($showMode === 'full')
                <div class="overflow-x-auto mt-4">
                    <table class="erp-ui-data-table min-w-full">
                        <thead>
                            <tr>
                                <th>کالا</th>
                                <th>واحد</th>
                                <th>تعداد</th>
                                <th>فی (ریال)</th>
                                <th>مبلغ (ریال)</th>
                                <th>شرح</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($showingDocument->lines as $line)
                                <tr>
                                    <td>{{ $line->item?->name ?: '-' }}</td>
                                    <td>{{ $line->item?->unit?->name ?: '-' }}</td>
                                    <td>{{ formatQuantity((float) $line->quantity) }}</td>
                                    <td>{{ formatMoney((float) $line->unit_price) }}</td>
                                    <td>{{ formatMoney((float) $line->line_total) }}</td>
                                    <td>{{ $line->description ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-slate-500 py-4">ردیفی ثبت نشده است.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </x-erp.ui.details-modal>
    @endif

    <style>
        .erp-shell .inventory-documents-filter-row {
            grid-template-columns: minmax(0, 1.35fr) minmax(0, 0.8fr) minmax(0, 0.72fr) minmax(0, 0.72fr) minmax(0, 0.85fr) auto;
        }

        .erp-shell .inventory-documents-filter-row .erp-filter-actions {
            flex-wrap: nowrap;
        }

        @media (max-width: 1023px) {
            .erp-shell .inventory-documents-filter-row {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .erp-shell .inventory-documents-filter-row .erp-filter-actions {
                grid-column: 1 / -1;
            }
        }
    </style>
</x-erp.ui.list-page>
