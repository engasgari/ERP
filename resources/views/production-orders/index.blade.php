@php
    $statusLabels = [
        'draft' => 'پیش‌نویس',
        'approved' => 'تایید شده',
        'in_progress' => 'در حال تولید',
        'testing' => 'کنترل کیفیت',
        'completed' => 'تکمیل شده',
        'closed' => 'بسته شده',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">سفارش‌های تولید پروژه</h2>
    </x-slot>

    <div class="py-10">
        <div class="rounded-lg bg-white p-6 shadow space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-black text-slate-800">لیست سفارش‌های تولید</h2>
                    <p class="mt-1 text-sm text-slate-500">سفارش تولید به پروژه وصل است و مواد مصرفی آن در بهای تمام‌شده پروژه می‌آید.</p>
                </div>
                <a href="{{ route('production-orders.create') }}" class="erp-action-btn erp-action-edit">سفارش جدید</a>
            </div>

            <form method="GET" action="{{ route('production-orders.index') }}" class="erp-ui-filter-bar">
                <div class="erp-filter-row">
                    <label class="erp-filter-field md:col-span-2">جستجو
                        <input name="search" value="{{ request('search') }}" placeholder="شماره سفارش، پروژه یا محصول">
                    </label>
                    <label class="erp-filter-field">پروژه
                        <select name="project_id">
                            <option value="">همه پروژه‌ها</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}" @selected(request('project_id') == $project->id)>{{ $project->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="erp-filter-field">وضعیت
                        <select name="status">
                            <option value="">همه وضعیت‌ها</option>
                            @foreach($statusLabels as $value => $label)
                                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <x-filter-actions :reset-route="route('production-orders.index')" />
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="erp-ui-data-table">
                    <thead>
                        <tr>
                            <th>شماره</th>
                            <th>پروژه</th>
                            <th>محصول</th>
                            <th>تعداد</th>
                            <th>وضعیت</th>
                            <th>مصرف ثبت‌شده</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                            <tr>
                                <td class="font-bold">{{ $order->number }}</td>
                                <td>{{ $order->project?->name ?: '-' }}</td>
                                <td>{{ $order->item?->name ?: '-' }}</td>
                                <td>{{ number_format((float) $order->quantity, 3) }}</td>
                                <td>{{ $order->status_label }}</td>
                                <td>{{ number_format($order->consumed_materials_count) }} از {{ number_format($order->material_consumptions_count) }}</td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        <a href="{{ route('production-orders.show', $order) }}" class="erp-action-btn erp-action-detail">جزئیات</a>
                                        <a href="{{ route('production-orders.edit', $order) }}" class="erp-action-btn erp-action-edit">ویرایش</a>
                                        <form method="POST" action="{{ route('production-orders.destroy', $order) }}" onsubmit="return confirm('سفارش تولید حذف شود؟')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="erp-action-btn erp-action-delete">حذف</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-slate-500 py-6">سفارشی ثبت نشده است.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $orders->links() }}</div>
        </div>
    </div>
</x-app-layout>
