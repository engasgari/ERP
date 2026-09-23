<x-erp.ui.list-page
    title="تراکنش‌های خزانه"
    description="دریافت، پرداخت و انتقال بین بانک و صندوق با ثبت خودکار سند حسابداری."
    route="treasury.index"
    :actions="[
        ['label' => 'ثبت تراکنش خزانه', 'url' => route('treasury.create'), 'class' => 'erp-action-edit'],
        ['label' => 'حساب جاری شرکا', 'url' => route('partner-current-accounts.index'), 'class' => 'erp-action-detail'],
    ]"
>
    <x-slot name="filters">
        <x-erp.ui.filter-bar wire:submit.prevent>
            <div class="erp-filter-row treasury-filter-row">
                <label class="erp-filter-field">جستجو
                    <input wire:model.live.debounce.400ms="search" placeholder="شماره، شرح، شخص یا سند حسابداری">
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
        :headers="['شماره', 'تاریخ', 'نوع', 'مبلغ (ریال)', 'شخص/شرکت', 'وضعیت', 'سند حسابداری', 'عملیات']"
        empty-message="تراکنشی ثبت نشده است."
        :colspan="8"
    >
        @foreach($transactions as $transaction)
            <tr wire:key="treasury-{{ $transaction->id }}">
                <td class="text-nowrap font-semibold">{{ $transaction->number }}</td>
                <td class="text-nowrap">{{ gregorianToJalaliDate($transaction->transaction_date) }}</td>
                <td>{{ $typeLabels[$transaction->type] ?? $transaction->type }}</td>
                <td class="text-nowrap">{{ formatMoney((float) $transaction->amount) }}</td>
                <td>{{ $transaction->party?->name ?: '-' }}</td>
                <td>
                    <x-erp.ui.status-badge
                        :label="$statusLabels[$transaction->status] ?? $transaction->status"
                        :tone="match ($transaction->status) {
                            'posted' => 'success',
                            'void' => 'danger',
                            default => 'neutral',
                        }"
                    />
                </td>
                <td>
                    @if($transaction->accountingDocument)
                        <a href="{{ route('accounting-documents.show', $transaction->accountingDocument) }}" class="erp-modal-trigger text-blue-700 font-semibold">
                            {{ $transaction->accountingDocument->number }}
                        </a>
                    @else
                        -
                    @endif
                </td>
                <td>
                    <x-erp.ui.row-actions>
                        <x-erp.ui.row-action icon="edit" label="ویرایش" :href="route('treasury.edit', $transaction)" />
                        <x-erp.ui.row-action
                            icon="delete"
                            label="حذف"
                            tone="danger"
                            wire:click="delete({{ $transaction->id }})"
                            wire:confirm="تراکنش خزانه و سند حسابداری وابسته حذف شوند؟"
                            wire:loading.attr="disabled"
                            wire:target="delete({{ $transaction->id }})"
                        />
                    </x-erp.ui.row-actions>
                </td>
            </tr>
        @endforeach
    </x-erp.ui.data-table>

    <div>{{ $transactions->links() }}</div>

    <style>
        .erp-shell .treasury-filter-row {
            grid-template-columns: minmax(0, 1.25fr) minmax(0, 0.78fr) minmax(0, 0.78fr) minmax(0, 0.82fr) minmax(0, 0.82fr) auto;
        }

        .erp-shell .treasury-filter-row .erp-filter-actions {
            flex-wrap: nowrap;
        }

        @media (max-width: 1023px) {
            .erp-shell .treasury-filter-row {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .erp-shell .treasury-filter-row .erp-filter-actions {
                grid-column: 1 / -1;
            }
        }
    </style>
</x-erp.ui.list-page>
