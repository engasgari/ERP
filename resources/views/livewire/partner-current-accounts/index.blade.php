<x-erp.ui.list-page
    title="حساب جاری شرکا"
    description="واریز و برداشت بین حساب بانکی و حساب جاری شریک با ثبت خودکار سند حسابداری."
    route="partner-current-accounts.index"
    :actions="[
        ['label' => 'ثبت انتقال جدید', 'url' => route('partner-current-accounts.create'), 'class' => 'erp-action-edit'],
    ]"
>
    <x-slot name="filters">
        <x-erp.ui.filter-bar wire:submit.prevent>
            <div class="erp-filter-row partner-current-accounts-filter-row">
                <label class="erp-filter-field">جستجو
                    <input wire:model.live.debounce.400ms="search" placeholder="شماره سند، شرح یا نام شریک">
                </label>
                <label class="erp-filter-field">شریک
                    <select wire:model.live="party_id">
                        <option value="">همه</option>
                        @foreach($partners as $partner)
                            <option value="{{ $partner->id }}">{{ $partner->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="erp-filter-field">نوع
                    <select wire:model.live="direction">
                        <option value="">همه</option>
                        @foreach($directionLabels as $value => $label)
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
        :headers="['شماره سند', 'تاریخ', 'نوع', 'شریک', 'حساب بانکی', 'مبلغ (ریال)', 'عملیات']"
        empty-message="انتقالی ثبت نشده است."
        :colspan="7"
    >
        @foreach($transfers as $transfer)
            @php
                $direction = \App\Livewire\PartnerCurrentAccounts\Index::transferDirection($transfer);
            @endphp
            <tr wire:key="partner-transfer-{{ $transfer->id }}">
                <td class="text-nowrap font-semibold">{{ $transfer->number }}</td>
                <td class="text-nowrap">{{ gregorianToJalaliDate($transfer->document_date) }}</td>
                <td>{{ $directionLabels[$direction] ?? '-' }}</td>
                <td>{{ \App\Livewire\PartnerCurrentAccounts\Index::transferPartnerName($transfer) }}</td>
                <td>{{ \App\Livewire\PartnerCurrentAccounts\Index::transferBankLabel($transfer) }}</td>
                <td class="text-nowrap">{{ formatMoney(\App\Livewire\PartnerCurrentAccounts\Index::transferAmount($transfer)) }}</td>
                <td>
                    <x-erp.ui.row-actions>
                        <x-erp.ui.row-action icon="document" label="مشاهده سند" :href="route('accounting-documents.show', $transfer)" />
                    </x-erp.ui.row-actions>
                </td>
            </tr>
        @endforeach
    </x-erp.ui.data-table>

    <div>{{ $transfers->links() }}</div>

    <style>
        .erp-shell .partner-current-accounts-filter-row {
            grid-template-columns: minmax(0, 1.15fr) minmax(0, 0.9fr) minmax(0, 0.75fr) minmax(0, 0.82fr) minmax(0, 0.82fr) auto;
        }

        .erp-shell .partner-current-accounts-filter-row .erp-filter-actions {
            flex-wrap: nowrap;
        }

        @media (max-width: 1023px) {
            .erp-shell .partner-current-accounts-filter-row {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .erp-shell .partner-current-accounts-filter-row .erp-filter-actions {
                grid-column: 1 / -1;
            }
        }
    </style>
</x-erp.ui.list-page>
