@php
    $documentTypeLabels = [
        'receipt' => 'رسید انبار',
        'issue' => 'حواله خروج',
        'consumption' => 'حواله مصرف',
        'transfer' => 'انتقال بین انبار',
    ];

    $statusLabels = ['confirmed' => 'تایید شده', 'draft' => 'موقت'];
    $entryModeLabels = ['manual' => 'دستی', 'automatic' => 'اتوماتیک'];

    $sourceMeta = function ($document) {
        if (! $document->source_type) {
            return [
                'label' => $document->is_automatic ? 'ثبت سیستمی' : 'دستی',
                'detail' => null,
            ];
        }

        $source = $document->source;

        if ($document->source_type === \App\Models\Invoice::class) {
            return [
                'label' => 'فاکتور ' . ($source?->number ?: '#' . $document->source_id),
                'detail' => $source ? (($source->direction === 'sale' ? 'فروش' : 'خرید') . ' - ' . ($source->party?->name ?: 'طرف حساب حذف شده')) : null,
            ];
        }

        if ($document->source_type === \App\Models\ProductionOrder::class) {
            return [
                'label' => 'سفارش تولید ' . ($source?->number ?: '#' . $document->source_id),
                'detail' => $source?->project?->name,
            ];
        }

        if ($document->source_type === \App\Models\Item::class) {
            return [
                'label' => 'موجودی اولیه کالا ' . ($source?->name ?: '#' . $document->source_id),
                'detail' => $source?->code,
            ];
        }

        return [
            'label' => class_basename($document->source_type) . ' #' . $document->source_id,
            'detail' => null,
        ];
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">اسناد انبار</h2>
    </x-slot>

    <div class="bg-white rounded-lg shadow-md p-6 space-y-4">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="text-lg font-bold">فهرست رسید و حواله انبار</h2>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('inventory-documents.create', ['type' => 'receipt']) }}" class="erp-action-btn erp-action-edit">رسید انبار</a>
                <a href="{{ route('inventory-documents.create', ['type' => 'issue']) }}" class="erp-action-btn erp-action-edit">حواله خروج</a>
                <a href="{{ route('inventory-documents.create', ['type' => 'consumption']) }}" class="erp-action-btn erp-action-edit">حواله مصرف</a>
                <a href="{{ route('inventory-documents.create', ['type' => 'transfer']) }}" class="erp-action-btn erp-action-edit">انتقال بین انبار</a>
            </div>
        </div>

        @if(session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-red-800">
                <div>{{ session('error') }}</div>
                @if(session('error_details'))
                    <ul class="mt-2 list-disc pr-5 space-y-1">
                        @foreach(session('error_details') as $detail)
                            <li>{{ $detail }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        <form method="GET" action="{{ route('inventory-documents.index') }}" class="erp-ui-filter-bar">
            <div class="erp-filter-row">
                <label class="erp-filter-field md:col-span-2">جستجو
                    <input name="search" value="{{ request('search') }}" placeholder="شماره سند، توضیح، نام کالا">
                </label>
                <label class="erp-filter-field">نوع سند
                    <select name="type">
                        <option value="">همه</option>
                        @foreach($documentTypeLabels as $value => $label)
                            <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="erp-filter-field">منبع
                    <select name="entry_mode">
                        <option value="">همه</option>
                        <option value="manual" @selected(request('entry_mode') === 'manual')>دستی</option>
                        <option value="automatic" @selected(request('entry_mode') === 'automatic')>اتوماتیک</option>
                    </select>
                </label>
                <label class="erp-filter-field">وضعیت
                    <select name="status">
                        <option value="">همه</option>
                        <option value="draft" @selected(request('status') === 'draft')>موقت</option>
                        <option value="confirmed" @selected(request('status') === 'confirmed')>تایید شده</option>
                    </select>
                </label>
                <label class="erp-filter-field">انبار
                    <select name="warehouse_id">
                        <option value="">همه</option>
                        @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected((string) request('warehouse_id') === (string) $warehouse->id)>{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </label>
                <x-filter-actions :reset-route="route('inventory-documents.index')" />
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="erp-ui-data-table min-w-[1050px]">
                <thead>
                    <tr>
                        <th>شماره سند</th>
                        <th>تاریخ</th>
                        <th>نوع</th>
                        <th>انبار</th>
                        <th>پروژه</th>
                        <th>منبع</th>
                        <th>اقلام</th>
                        <th>مبلغ (ریال)</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($documents as $document)
                        @php($source = $sourceMeta($document))
                        <tr>
                            <td>
                                <button type="button" class="erp-modal-trigger" data-erp-modal-open="inventory-document-{{ $document->id }}">{{ $document->number }}</button>
                            </td>
                            <td>{{ gregorianToJalaliDate($document->document_date) }}</td>
                            <td>{{ $documentTypeLabels[$document->type] ?? $document->type }}</td>
                            <td>
                                <div>{{ $document->warehouse?->name ?: '-' }}</div>
                                @if($document->targetWarehouse)
                                    <div class="text-xs text-gray-500">به: {{ $document->targetWarehouse->name }}</div>
                                @endif
                            </td>
                            <td>{{ $document->project?->name ?: '-' }}</td>
                            <td>
                                <div>{{ $document->is_automatic ? 'اتوماتیک' : 'دستی' }}</div>
                                @if($document->is_automatic)
                                    <button type="button" class="erp-modal-trigger text-xs text-gray-600" data-erp-modal-open="inventory-document-{{ $document->id }}">{{ $source['label'] }}</button>
                                    @if($source['detail'])
                                        <div class="text-xs text-gray-500">{{ $source['detail'] }}</div>
                                    @endif
                                @endif
                            </td>
                            <td>{{ $document->lines->count() }}</td>
                            <td>{{ number_format((float) $document->lines->sum('line_total')) }}</td>
                            <td>{{ $statusLabels[$document->status] ?? $document->status }}</td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    <button type="button" class="erp-action-btn erp-action-detail" data-erp-modal-open="inventory-document-{{ $document->id }}">جزئیات</button>
                                    @if(! $document->is_automatic)
                                        <a href="{{ route('inventory-documents.edit', $document) }}" class="erp-action-btn erp-action-edit">ویرایش</a>
                                        <form method="POST" action="{{ route('inventory-documents.destroy', $document) }}" onsubmit="return confirm('آیا از حذف این سند انبار مطمئن هستید؟')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="erp-action-btn erp-action-delete">حذف</button>
                                        </form>
                                    @else
                                        <span class="text-xs text-gray-500">سیستمی</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-gray-500 py-6">سند انباری یافت نشد.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $documents->links() }}</div>
    </div>

    @foreach($documents as $document)
        @php($source = $sourceMeta($document))
        <div class="erp-ui-modal-backdrop" id="inventory-document-{{ $document->id }}" hidden>
            <div class="erp-ui-modal-panel">
                <div class="erp-modal-header">
                    <h3>سند انبار {{ $document->number }}</h3>
                    <button type="button" class="erp-modal-close" data-erp-modal-close>×</button>
                </div>
                <div class="erp-modal-body space-y-4">
                    <div class="erp-modal-grid">
                        <div class="erp-modal-field">شماره سند<div class="erp-modal-value">{{ $document->number }}</div></div>
                        <div class="erp-modal-field">تاریخ<div class="erp-modal-value">{{ gregorianToJalaliDate($document->document_date) }}</div></div>
                        <div class="erp-modal-field">نوع سند<div class="erp-modal-value">{{ $documentTypeLabels[$document->type] ?? $document->type }}</div></div>
                        <div class="erp-modal-field">انبار<div class="erp-modal-value">{{ $document->warehouse?->name ?: '-' }}</div></div>
                        <div class="erp-modal-field">انبار مقصد<div class="erp-modal-value">{{ $document->targetWarehouse?->name ?: '-' }}</div></div>
                        <div class="erp-modal-field">پروژه<div class="erp-modal-value">{{ $document->project?->name ?: '-' }}</div></div>
                        <div class="erp-modal-field">وضعیت<div class="erp-modal-value">{{ $statusLabels[$document->status] ?? $document->status }}</div></div>
                        <div class="erp-modal-field">منبع<div class="erp-modal-value">{{ $entryModeLabels[$document->entry_mode] ?? $document->entry_mode }}</div></div>
                        <div class="erp-modal-field">مرجع<div class="erp-modal-value">{{ $source['label'] }}{{ $source['detail'] ? ' - ' . $source['detail'] : '' }}</div></div>
                        <div class="erp-modal-field">سند حسابداری<div class="erp-modal-value">{{ $document->accountingDocument?->number ?: '-' }}</div></div>
                        <div class="erp-modal-field md:col-span-2">توضیحات<div class="erp-modal-value">{{ $document->description ?: '-' }}</div></div>
                    </div>

                    <div class="overflow-x-auto">
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
                                @foreach($document->lines as $line)
                                    <tr>
                                        <td>{{ $line->item?->name ?: '-' }}</td>
                                        <td>{{ $line->item?->unit?->name ?: '-' }}</td>
                                        <td>{{ number_format((float) $line->quantity, 3) }}</td>
                                        <td>{{ number_format((float) $line->unit_price) }}</td>
                                        <td>{{ number_format((float) $line->line_total) }}</td>
                                        <td>{{ $line->description ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="erp-modal-actions">
                    @if(! $document->is_automatic)
                        <a href="{{ route('inventory-documents.edit', $document) }}" class="erp-action-btn erp-action-edit">ویرایش</a>
                    @endif
                    <button type="button" class="erp-action-btn erp-action-detail" data-erp-modal-close>بستن</button>
                </div>
            </div>
        </div>
    @endforeach

    <x-erp-modal-script />
</x-app-layout>
