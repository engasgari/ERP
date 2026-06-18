@php
    $typeLabels = [
        'receipt' => 'رسید انبار',
        'issue' => 'حواله خروج',
        'consumption' => 'حواله مصرف',
        'transfer' => 'انتقال بین انبار',
    ];

    $documents = $warehouse->inventoryDocuments
        ->sortByDesc('document_date')
        ->take(10);
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">جزئیات انبار</h2>
    </x-slot>

    <div class="space-y-6">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex flex-wrap justify-between items-start gap-4 mb-6">
                <div>
                    <h2 class="text-2xl font-bold">{{ $warehouse->name }}</h2>
                    <div class="text-sm text-gray-600 mt-2">
                        وضعیت: {{ $warehouse->is_active ? 'فعال' : 'غیرفعال' }} |
                        تاریخ ایجاد: {{ $warehouse->created_at ? verta($warehouse->created_at)->format('Y/m/d') : '-' }}
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('inventory-documents.create', ['type' => 'receipt', 'warehouse_id' => $warehouse->id]) }}" class="erp-action-btn erp-action-edit">رسید انبار</a>
                    <a href="{{ route('inventory-documents.create', ['type' => 'issue', 'warehouse_id' => $warehouse->id]) }}" class="erp-action-btn erp-action-edit">حواله خروج</a>
                    <a href="{{ route('warehouses.edit', $warehouse) }}" class="erp-action-btn erp-action-edit">ویرایش</a>
                    <a href="{{ route('warehouses.index') }}" class="erp-action-btn">بازگشت</a>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="rounded-lg border border-gray-200 bg-slate-50 p-4">
                    <div class="text-sm text-gray-600">تعداد کالاهای موجود</div>
                    <div class="text-xl font-bold mt-2">{{ count($warehouse->available_items) }}</div>
                </div>
                <div class="rounded-lg border border-gray-200 bg-slate-50 p-4">
                    <div class="text-sm text-gray-600">تعداد اسناد انبار</div>
                    <div class="text-xl font-bold mt-2">{{ $warehouse->inventoryDocuments->count() }}</div>
                </div>
                <div class="rounded-lg border border-gray-200 bg-slate-50 p-4">
                    <div class="text-sm text-gray-600">ارزش موجودی</div>
                    <div class="text-xl font-bold mt-2">{{ number_format(collect($warehouse->available_items)->sum('total_value')) }}</div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="font-semibold mb-4">موجودی کالاها</h3>

            @if(count($warehouse->available_items) > 0)
                <div class="overflow-x-auto">
                    <table class="erp-ui-data-table w-full">
                        <thead>
                            <tr>
                                <th>کالا</th>
                                <th>دسته‌بندی</th>
                                <th>موجودی</th>
                                <th>میانگین قیمت (ریال)</th>
                                <th>ارزش کل</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($warehouse->available_items as $itemName => $itemData)
                                <tr>
                                    <td>{{ $itemName }}</td>
                                    <td>{{ $itemData['category'] ?? '-' }}</td>
                                    <td>{{ number_format($itemData['quantity'], 3) }}</td>
                                    <td>{{ number_format($itemData['average_price']) }}</td>
                                    <td>{{ number_format($itemData['total_value']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-gray-500 text-center py-4">موجودی قابل نمایش برای این انبار ثبت نشده است.</p>
            @endif
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
                <h3 class="font-semibold">آخرین اسناد انبار</h3>
                <a href="{{ route('inventory-documents.index', ['warehouse_id' => $warehouse->id]) }}" class="erp-action-btn">مشاهده همه</a>
            </div>

            @if($documents->count() > 0)
                <div class="overflow-x-auto">
                    <table class="erp-ui-data-table w-full">
                        <thead>
                            <tr>
                                <th>شماره سند</th>
                                <th>تاریخ</th>
                                <th>نوع سند</th>
                                <th>پروژه</th>
                                <th>وضعیت</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($documents as $document)
                                <tr>
                                    <td>
                                        <a href="{{ route('inventory-documents.index', ['search' => $document->number]) }}" class="erp-modal-trigger">{{ $document->number }}</a>
                                    </td>
                                    <td>{{ gregorianToJalaliDate($document->document_date) }}</td>
                                    <td>{{ $typeLabels[$document->type] ?? $document->type }}</td>
                                    <td>{{ $document->project?->name ?: '-' }}</td>
                                    <td>{{ $document->status === 'confirmed' ? 'تایید شده' : 'موقت' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-gray-500 text-center py-4">هنوز سندی برای این انبار ثبت نشده است.</p>
            @endif
        </div>
    </div>
</x-app-layout>
