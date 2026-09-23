<x-erp.ui.list-page
    title="اسناد حسابداری"
    description="فهرست اسناد دستی و خودکار با امکان مشاهده، چاپ و ثبت سند جدید."
    route="accounting-documents.index"
    :actions="[
        ['label' => 'ثبت سند حسابداری', 'url' => route('accounting-documents.create'), 'class' => 'erp-action-edit'],
    ]"
>
    <x-slot name="filters">
        <x-erp.ui.filter-bar wire:submit.prevent>
            <div class="erp-filter-row accounting-documents-filter-row">
                <label class="erp-filter-field">جستجو
                    <input wire:model.live.debounce.400ms="search" placeholder="شماره سند، شرح یا سرفصل">
                </label>
                <label class="erp-filter-field">نوع
                    <select wire:model.live="type">
                        <option value="">همه</option>
                        @foreach($typeLabels as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="erp-filter-field">وضعیت
                    <select wire:model.live="status">
                        <option value="">همه</option>
                        @foreach($statusLabels as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="erp-filter-field">از تاریخ
                    <x-erp.ui.jalali-date-input wire:model.live.debounce.500ms="date_from" placeholder="1405/01/01" class="w-full" />
                </label>
                <label class="erp-filter-field">تا تاریخ
                    <x-erp.ui.jalali-date-input wire:model.live.debounce.500ms="date_to" placeholder="1405/12/29" class="w-full" />
                </label>
                <div class="erp-filter-actions">
                    <button type="button" wire:click="clearFilters" class="erp-action-btn">حذف فیلترها</button>
                </div>
            </div>
        </x-erp.ui.filter-bar>

        @foreach(array_filter($dateErrors ?? []) as $error)
            <div class="erp-filter-error">{{ $error }}</div>
        @endforeach
    </x-slot>

    <x-erp.ui.data-table
        :headers="['شماره سند', 'تاریخ', 'نوع', 'تفصیل', 'وضعیت', 'بدهکار (ریال)', 'بستانکار (ریال)', 'عملیات']"
        empty-message="سندی ثبت نشده است."
        :colspan="8"
    >
        @foreach($items as $document)
            @php
                $detailAccounts = $document->lines
                    ->pluck('detailAccount')
                    ->filter()
                    ->unique('id')
                    ->map(fn ($detail) => chartAccountDisplayLabel($detail))
                    ->values();
                $statusTone = match ($document->status) {
                    'posted' => 'success',
                    'void' => 'danger',
                    default => 'warning',
                };
            @endphp
            <tr wire:key="accounting-document-{{ $document->id }}">
                <td class="text-nowrap font-semibold">{{ $document->number }}</td>
                <td class="text-nowrap">{{ gregorianToJalaliDate($document->document_date) }}</td>
                <td>{{ $typeLabels[$document->type] ?? $document->type }}</td>
                <td>{{ $detailAccounts->isNotEmpty() ? $detailAccounts->implode('، ') : '-' }}</td>
                <td>
                    <x-erp.ui.status-badge
                        :label="$statusLabels[$document->status] ?? $document->status"
                        :tone="$statusTone"
                    />
                </td>
                <td class="text-nowrap">{{ formatMoney((float) $document->lines->sum('debit')) }}</td>
                <td class="text-nowrap">{{ formatMoney((float) $document->lines->sum('credit')) }}</td>
                <td>
                    <x-erp.ui.row-actions>
                        <x-erp.ui.row-action icon="view" label="مشاهده" :href="route('accounting-documents.show', $document)" />
                        <x-erp.ui.row-action icon="print" label="چاپ" :href="route('accounting-documents.print', $document)" target="_blank" />
                    </x-erp.ui.row-actions>
                </td>
            </tr>
        @endforeach
    </x-erp.ui.data-table>

    <div>{{ $items->links() }}</div>

    <style>
        .erp-shell .accounting-documents-filter-row {
            grid-template-columns: minmax(0, 1.25fr) minmax(0, 0.78fr) minmax(0, 0.78fr) minmax(0, 0.82fr) minmax(0, 0.82fr) auto;
        }

        .erp-shell .accounting-documents-filter-row .erp-filter-actions {
            flex-wrap: nowrap;
        }

        @media (max-width: 1023px) {
            .erp-shell .accounting-documents-filter-row {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .erp-shell .accounting-documents-filter-row .erp-filter-actions {
                grid-column: 1 / -1;
            }
        }
    </style>
</x-erp.ui.list-page>
