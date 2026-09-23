<x-erp.ui.list-page
    title="خرید خدمات پیمانکار از فاکتور فروش"
    description="فاکتورهای فروش خدمت را به پیمانکار تخصیص دهید؛ چند فاکتور را تجمیع کنید تا یک فاکتور خرید خدمات (همان کالای خدمت) ساخته شود."
    route="commerce.contractor-service-purchases.index"
    :showLoading="false"
>
    <x-slot name="filters">
        <x-erp.ui.filter-bar wire:submit.prevent>
            <div class="erp-filter-row">
                <label class="erp-filter-field md:col-span-2">جستجو
                    <input wire:model.live.debounce.400ms="search" placeholder="شماره فاکتور یا نام مشتری">
                </label>
                <label class="erp-filter-field">پروژه
                    <select wire:model.live="project_id">
                        <option value="">همه</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}">{{ $project->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="erp-filter-field">سال مالی
                    <select wire:model.live="fiscal_year_id">
                        <option value="">همه</option>
                        @foreach($fiscalYears as $year)
                            <option value="{{ $year->id }}">{{ $year->title }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="erp-filter-field">پیمانکار
                    <select wire:model.live="contractor_party_id">
                        <option value="">همه</option>
                        <option value="none">بدون پیمانکار</option>
                        @foreach($contractors as $contractor)
                            <option value="{{ $contractor->id }}">{{ $contractor->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="erp-filter-field flex items-end gap-2 pb-2">
                    <input type="checkbox" wire:model.live="include_purchased" class="rounded border-slate-300">
                    <span>نمایش فاکتورهایی که خریدشان ثبت شده</span>
                </label>
                <div class="erp-filter-actions">
                    <button type="button" wire:click="clearFilters" class="erp-action-btn">حذف فیلترها</button>
                </div>
            </div>
        </x-erp.ui.filter-bar>
    </x-slot>

    @if(auth()->user()?->hasPermission('commerce.manage'))
        <div class="mb-4 flex justify-end">
            <button
                type="button"
                wire:click="createPurchaseInvoice"
                wire:confirm="فاکتور خرید خدمات موقت از فاکتورهای انتخاب‌شده ساخته شود؟"
                class="erp-action-btn erp-action-edit"
                @disabled(count($selectedSaleIds) === 0)
            >صدور فاکتور خرید از انتخاب‌شده‌ها ({{ count($selectedSaleIds) }})</button>
        </div>
        @if(count($selectedSaleIds) > 0)
            <p class="mb-3 text-sm text-slate-600">{{ count($selectedSaleIds) }} فاکتور انتخاب شده — تیک‌ها تا زمان صدور فاکتور خرید حفظ می‌شوند.</p>
        @endif
    @endif

    <div wire:loading.delay.short wire:target="selectedSaleIds,assignContractor" class="mb-2 text-sm text-slate-500">در حال ذخیره…</div>

    <x-erp.ui.data-table
        :headers="['', 'شماره', 'تاریخ', 'مشتری', 'پروژه', 'پیمانکار', 'وضعیت خرید']"
        empty-message="فاکتور فروش خدمت تأییدشده‌ای یافت نشد."
        :colspan="7"
    >
        @foreach($items as $invoice)
            @php
                $purchased = (bool) $invoice->contractorAllocation?->purchase_invoice_id;
                $purchase = $invoice->contractorAllocation?->purchaseInvoice;
            @endphp
            <tr wire:key="csp-sale-{{ $invoice->id }}" @class(['opacity-60' => $purchased])>
                <td>
                    @if(! $purchased && auth()->user()?->hasPermission('commerce.manage'))
                        <input
                            type="checkbox"
                            wire:model.live="selectedSaleIds"
                            wire:loading.attr="disabled"
                            wire:target="selectedSaleIds,createPurchaseInvoice,assignContractor"
                            value="{{ $invoice->id }}"
                            aria-label="انتخاب فاکتور {{ $invoice->number }}"
                        >
                    @endif
                </td>
                <td class="font-semibold text-nowrap" dir="ltr">{{ $invoice->number }}</td>
                <td class="text-nowrap">{{ gregorianToJalaliDate($invoice->invoice_date) }}</td>
                <td>{{ $invoice->party?->name ?? '-' }}</td>
                <td>{{ $invoice->project?->name ?? '-' }}</td>
                <td>
                    @if($purchased)
                        {{ $invoice->contractorAllocation?->contractor?->name ?? '-' }}
                    @elseif(auth()->user()?->hasPermission('commerce.manage'))
                        <select
                            class="w-full min-w-[10rem] rounded border-slate-300 text-sm"
                            wire:change="assignContractor({{ $invoice->id }}, $event.target.value)"
                        >
                            <option value="">— پیمانکار —</option>
                            @foreach($contractors as $contractor)
                                <option
                                    value="{{ $contractor->id }}"
                                    @selected($invoice->contractorAllocation?->contractor_party_id === $contractor->id)
                                >{{ $contractor->name }}</option>
                            @endforeach
                        </select>
                    @else
                        {{ $invoice->contractorAllocation?->contractor?->name ?? '-' }}
                    @endif
                </td>
                <td>
                    @if($purchased && $purchase)
                        <x-erp.ui.status-badge label="ثبت شده" tone="success" />
                        <a href="{{ route('invoices.show', $purchase) }}" class="text-sm text-sky-700 hover:underline">{{ $purchase->number }}</a>
                    @elseif($invoice->contractorAllocation?->contractor_party_id)
                        <x-erp.ui.status-badge label="در انتظار صدور خرید" tone="warning" />
                    @else
                        <x-erp.ui.status-badge label="بدون پیمانکار" tone="neutral" />
                    @endif
                </td>
            </tr>
        @endforeach
    </x-erp.ui.data-table>

    @if($items->hasPages())
        <div class="mt-4">{{ $items->links() }}</div>
    @endif
</x-erp.ui.list-page>
