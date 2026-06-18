@php
    $statusLabels = [
        'draft' => 'پیش‌نویس',
        'active' => 'فعال',
        'archived' => 'بایگانی‌شده',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">فرمول ساخت (BOM)</h2>
    </x-slot>

    <div class="py-10">
        <div class="rounded-lg bg-white p-6 shadow space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-black text-slate-800">فرمول‌های ساخت</h2>
                    <p class="mt-1 text-sm text-slate-500">فرمول ساخت محصول برای سفارش‌های تولید پروژه استفاده می‌شود.</p>
                </div>
                <a href="{{ route('project-boms.create') }}" class="erp-action-btn erp-action-edit">فرمول جدید</a>
            </div>

            <form method="GET" action="{{ route('project-boms.index') }}" class="erp-ui-filter-bar">
                <div class="erp-filter-row">
                    <label class="erp-filter-field md:col-span-2">جستجو
                        <input name="search" value="{{ request('search') }}" placeholder="محصول، کد کالا، نسخه یا بازنگری">
                    </label>
                    <label class="erp-filter-field">وضعیت
                        <select name="status">
                            <option value="">همه</option>
                            @foreach($statusLabels as $value => $label)
                                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <x-filter-actions :reset-route="route('project-boms.index')" />
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="erp-ui-data-table">
                    <thead>
                        <tr>
                            <th>محصول</th>
                            <th>نسخه</th>
                            <th>بازنگری</th>
                            <th>وضعیت</th>
                            <th>تعداد مواد</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($boms as $bom)
                            <tr>
                                <td class="font-bold">{{ $bom->item?->name ?: '-' }}</td>
                                <td>{{ $bom->version_number }}</td>
                                <td>{{ $bom->revision ?: '-' }}</td>
                                <td>{{ $bom->status_label }}</td>
                                <td>{{ $bom->lines->count() }}</td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        <a href="{{ route('project-boms.show', $bom) }}" class="erp-action-btn erp-action-detail">جزئیات</a>
                                        <a href="{{ route('project-boms.edit', $bom) }}" class="erp-action-btn erp-action-edit">ویرایش</a>
                                        @if((int) $bom->production_orders_count === 0)
                                            <form method="POST" action="{{ route('project-boms.destroy', $bom) }}" onsubmit="return confirm('فرمول ساخت حذف شود؟')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="erp-action-btn erp-action-delete">حذف</button>
                                            </form>
                                        @else
                                            <span class="text-xs text-slate-500">استفاده شده</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-slate-500 py-6">فرمول ساختی ثبت نشده است.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $boms->links() }}</div>
        </div>
    </div>
</x-app-layout>
