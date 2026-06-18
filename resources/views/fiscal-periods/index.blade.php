@php
    $statusLabels = ['open' => 'باز', 'closed' => 'بسته'];
    $editing = filled($editingYear);
    $formAction = $editing ? route('fiscal-periods.update', $editingYear) : route('fiscal-periods.store');
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">دوره‌های مالی</h2>
    </x-slot>

    <div class="space-y-6">
        @if(session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-red-800">{{ session('error') }}</div>
        @endif

        @if($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-red-800">
                <div class="font-semibold mb-2">لطفاً خطاهای فرم را بررسی کنید.</div>
                <ul class="list-disc pr-5 space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between gap-4 mb-5">
                <div>
                    <h3 class="font-bold text-lg">{{ $editing ? 'ویرایش دوره مالی' : 'تعریف دوره مالی' }}</h3>
                    <p class="text-sm text-gray-600 mt-1">تاریخ شروع و پایان را وارد کنید؛ سیستم فقط یک دوره مالی برای همین بازه می‌سازد.</p>
                </div>

                @if($editing)
                    <a href="{{ route('fiscal-periods.index') }}" class="erp-action-btn">انصراف</a>
                @endif
            </div>

            <form method="POST" action="{{ $formAction }}" class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
                @csrf
                @if($editing)
                    @method('PUT')
                @endif

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">عنوان</label>
                    <input type="text" name="title" value="{{ old('title', $editing ? $editingYear->title : '') }}"
                           placeholder="مثلاً دوره مالی ۱۴۰۵"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">تاریخ شروع</label>
                    <input type="text" name="start_date" value="{{ old('start_date', $editing ? gregorianToJalaliDate($editingYear->start_date) : '') }}"
                           placeholder="۱۴۰۵/۰۱/۰۱" required
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 ltr text-left">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">تاریخ پایان</label>
                    <input type="text" name="end_date" value="{{ old('end_date', $editing ? gregorianToJalaliDate($editingYear->end_date) : '') }}"
                           placeholder="۱۴۰۵/۱۲/۲۹" required
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 ltr text-left">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">واحد پول</label>
                    <input type="text" name="currency" value="{{ old('currency', $editing ? $editingYear->currency : 'IRR') }}"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">وضعیت</label>
                    <select name="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="open" @selected(old('status', $editing ? $editingYear->status : 'open') === 'open')>باز</option>
                        <option value="closed" @selected(old('status', $editing ? $editingYear->status : 'open') === 'closed')>بسته</option>
                    </select>
                </div>

                <div class="md:col-span-6 flex justify-end">
                    <button type="submit" class="erp-action-btn erp-action-edit">
                        {{ $editing ? 'ذخیره تغییرات' : 'ثبت دوره مالی' }}
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6 space-y-6">
            @forelse($years as $year)
                <div class="border border-gray-200 rounded-lg overflow-hidden">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 bg-slate-50 p-4">
                        <div>
                            <div class="font-bold text-lg">{{ $year->title }}</div>
                            <div class="text-sm text-gray-600 mt-1">
                                {{ gregorianToJalaliDate($year->start_date) }} تا {{ gregorianToJalaliDate($year->end_date) }} |
                                وضعیت: {{ $statusLabels[$year->status] ?? $year->status }}
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('fiscal-periods.index', ['edit' => $year->id]) }}" class="erp-action-btn erp-action-edit">ویرایش</a>
                            <form method="POST" action="{{ route('fiscal-periods.destroy', $year) }}"
                                  onsubmit="return confirm('آیا از حذف این دوره مالی مطمئن هستید؟ اگر سند مرتبط داشته باشد حذف نمی‌شود.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="erp-action-btn erp-action-delete">حذف</button>
                            </form>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="erp-ui-data-table w-full">
                            <thead>
                                <tr>
                                    <th>عنوان دوره</th>
                                    <th>شروع</th>
                                    <th>پایان</th>
                                    <th>وضعیت</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($year->periods as $period)
                                    <tr>
                                        <td>{{ $period->title }}</td>
                                        <td>{{ gregorianToJalaliDate($period->start_date) }}</td>
                                        <td>{{ gregorianToJalaliDate($period->end_date) }}</td>
                                        <td>{{ $statusLabels[$period->status] ?? $period->status }}</td>
                                        <td>
                                            @if($period->status === 'open')
                                                <form method="POST" action="{{ route('fiscal-periods.close', $period) }}">
                                                    @csrf
                                                    <button type="submit" class="erp-action-btn erp-action-delete">بستن دوره</button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('fiscal-periods.reopen', $period) }}">
                                                    @csrf
                                                    <button type="submit" class="erp-action-btn erp-action-edit">بازگشایی دوره</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="text-center text-gray-500 py-10">هنوز دوره مالی ثبت نشده است.</div>
            @endforelse
        </div>
    </div>
</x-app-layout>
