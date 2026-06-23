<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">فاکتورها و پیش‌فاکتورها</h2>
    </x-slot>

    <div class="bg-white rounded-lg shadow-md p-6 space-y-4">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="text-lg font-bold">فهرست اسناد بازرگانی</h2>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('invoices.create', ['direction' => 'sale', 'document_type' => 'proforma']) }}" class="erp-action-btn erp-action-edit">پیش‌فاکتور فروش</a>
                <a href="{{ route('invoices.create', ['direction' => 'sale']) }}" class="erp-action-btn erp-action-edit">فاکتور فروش</a>
                <a href="{{ route('invoices.create', ['direction' => 'purchase']) }}" class="erp-action-btn erp-action-edit">فاکتور خرید</a>
            </div>
        </div>

        <form method="GET" action="{{ route('invoices.index') }}" class="erp-ui-filter-bar">
            <div class="erp-filter-row">
                <label class="erp-filter-field md:col-span-2">جستجو
                    <input name="search" value="{{ request('search') }}" placeholder="شماره، طرف حساب، کالا، توضیح">
                </label>
                <label class="erp-filter-field">جهت
                    <select name="direction">
                        <option value="">همه</option>
                        <option value="sale" @selected(request('direction') === 'sale')>فروش</option>
                        <option value="purchase" @selected(request('direction') === 'purchase')>خرید</option>
                    </select>
                </label>
                <label class="erp-filter-field">نوع سند
                    <select name="document_type">
                        <option value="">همه</option>
                        <option value="invoice" @selected(request('document_type') === 'invoice')>فاکتور</option>
                        <option value="proforma" @selected(request('document_type') === 'proforma')>پیش‌فاکتور</option>
                    </select>
                </label>
                <label class="erp-filter-field">وضعیت
                    <select name="status">
                        <option value="">همه</option>
                        <option value="draft" @selected(request('status') === 'draft')>موقت</option>
                        <option value="confirmed" @selected(request('status') === 'confirmed')>تایید شده</option>
                    </select>
                </label>
                <x-filter-actions :reset-route="route('invoices.index')" />
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="erp-ui-data-table min-w-full">
                <thead>
                    <tr>
                        <th>شماره</th>
                        <th>نوع</th>
                        <th>تاریخ</th>
                        <th>طرف حساب</th>
                        <th>پروژه</th>
                        <th>مبلغ (ریال)</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                        <tr>
                            <td><button type="button" class="erp-modal-trigger" data-erp-modal-open="invoice-show-{{ $invoice->id }}">{{ $invoice->number }}</button></td>
                            <td>{{ $invoice->direction === 'sale' ? 'فروش' : 'خرید' }} / {{ $invoice->document_type === 'proforma' ? 'پیش‌فاکتور' : 'فاکتور' }}</td>
                            <td>{{ gregorianToJalaliDate($invoice->invoice_date) }}</td>
                            <td><button type="button" class="erp-modal-trigger" data-erp-modal-open="invoice-show-{{ $invoice->id }}">{{ $invoice->party?->name ?: 'طرف حساب حذف شده' }}</button></td>
                            <td>{{ $invoice->project?->code ? $invoice->project->code . ' - ' . $invoice->project->name : '-' }}</td>
                            <td>{{ number_format($invoice->total_amount) }}</td>
                            <td>{{ $invoice->settled_at ? 'تسویه شده' : ($invoice->status === 'draft' ? 'موقت' : ($invoice->status === 'confirmed' ? 'تایید شده' : $invoice->status)) }}</td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    <button type="button" class="erp-action-btn erp-action-detail" data-erp-modal-open="invoice-show-{{ $invoice->id }}">جزئیات</button>
                                    <a href="{{ route('invoices.show', $invoice) }}" class="erp-action-btn erp-action-edit">نمایش کامل</a>
                                    <a href="{{ route('invoices.edit', $invoice) }}" class="erp-action-btn erp-action-edit">ویرایش</a>
                                    @if($invoice->document_type === 'proforma')
                                        <form method="POST" action="{{ route('invoices.convert', $invoice) }}">@csrf<button class="erp-action-btn erp-action-edit">تبدیل به فاکتور</button></form>
                                    @elseif($invoice->status === 'draft')
                                        <form method="POST" action="{{ route('invoices.confirm', $invoice) }}">@csrf<button class="erp-action-btn erp-action-edit">تایید و سند</button></form>
                                    @elseif($invoice->status === 'confirmed' && ! $invoice->settled_at)
                                        <form method="POST" action="{{ route('invoices.settle', $invoice) }}">@csrf<button class="erp-action-btn erp-action-detail">تسویه</button></form>
                                    @elseif($invoice->settled_at)
                                        <form method="POST" action="{{ route('invoices.unsettle', $invoice) }}">@csrf<button class="erp-action-btn erp-action-detail">خروج از تسویه</button></form>
                                    @endif
                                    <form method="POST" action="{{ route('invoices.destroy', $invoice) }}" onsubmit="return confirm('فاکتور و همه سندهای مالی و انبار وابسته حذف شوند؟')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="erp-action-btn erp-action-delete">حذف</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-gray-500 py-6">فاکتوری یافت نشد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $invoices->links() }}</div>
    </div>

    @foreach($invoices as $invoice)
        <div class="erp-ui-modal-backdrop" id="invoice-show-{{ $invoice->id }}" hidden>
            <div class="erp-ui-modal-panel">
                <div class="erp-modal-header"><h3>فاکتور {{ $invoice->number }}</h3><button type="button" class="erp-modal-close" data-erp-modal-close>×</button></div>
                <div class="erp-modal-body space-y-4">
                    <div class="erp-modal-grid">
                        <div class="erp-modal-field">شماره<div class="erp-modal-value">{{ $invoice->number }}</div></div>
                        <div class="erp-modal-field">نوع<div class="erp-modal-value">{{ $invoice->direction === 'sale' ? 'فروش' : 'خرید' }} / {{ $invoice->document_type === 'proforma' ? 'پیش‌فاکتور' : 'فاکتور' }}</div></div>
                        <div class="erp-modal-field">تاریخ<div class="erp-modal-value">{{ gregorianToJalaliDate($invoice->invoice_date) }}</div></div>
                        <div class="erp-modal-field">طرف حساب<div class="erp-modal-value">{{ $invoice->party?->name ?: '-' }}</div></div>
                        <div class="erp-modal-field">پروژه<div class="erp-modal-value">{{ $invoice->project?->code ? $invoice->project->code . ' - ' . $invoice->project->name : '-' }}</div></div>
                        <div class="erp-modal-field">وضعیت<div class="erp-modal-value">{{ $invoice->settled_at ? 'تسویه شده' : ($invoice->status === 'draft' ? 'موقت' : ($invoice->status === 'confirmed' ? 'تایید شده' : $invoice->status)) }}</div></div>
                        <div class="erp-modal-field">مبلغ نهایی (ریال)<div class="erp-modal-value">{{ number_format($invoice->total_amount) }}</div></div>
                        <div class="erp-modal-field md:col-span-2">توضیحات<div class="erp-modal-value">{{ $invoice->description ?: '-' }}</div></div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="erp-ui-data-table min-w-full">
                            <thead><tr><th>کالا/خدمت</th><th>شرح</th><th>تعداد</th><th>قیمت واحد (ریال)</th><th>مبلغ ردیف (ریال)</th></tr></thead>
                            <tbody>
                                @foreach($invoice->lines as $line)
                                    <tr>
                                        <td>{{ $line->item?->name ?: '-' }}</td>
                                        <td>{{ $line->description ?: '-' }}</td>
                                        <td>{{ number_format((float) $line->quantity, 3) }}</td>
                                        <td>{{ number_format((float) $line->unit_price) }}</td>
                                        <td>{{ number_format((float) $line->line_total) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="erp-modal-actions">
                    <a href="{{ route('invoices.show', $invoice) }}" class="erp-action-btn erp-action-edit">نمایش کامل</a>
                    <a href="{{ route('invoices.edit', $invoice) }}" class="erp-action-btn erp-action-edit">ویرایش</a>
                    <a href="{{ route('invoices.print', $invoice) }}" target="_blank" data-no-spa class="erp-action-btn erp-action-detail">چاپ</a>
                    @if($invoice->settled_at)
                        <form method="POST" action="{{ route('invoices.unsettle', $invoice) }}">
                            @csrf
                            <button class="erp-action-btn erp-action-detail">خروج از تسویه</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @endforeach

    <x-erp-modal-script />
</x-app-layout>
