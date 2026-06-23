<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">جزئیات پروژه</h2>
    </x-slot>

    <div class="erp-project-detail py-10">
        <div class="space-y-5 rounded-lg bg-white p-6 shadow">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-black text-slate-900">{{ $project->name }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $project->description ?: 'بدون توضیحات' }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('projects.costing', $project) }}" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-bold text-white">بهای تمام‌شده</a>
                    <a href="{{ route('production-orders.create', ['project_id' => $project->id]) }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-bold text-white">ثبت سفارش تولید</a>
                    <a href="{{ route('projects.edit', $project) }}" class="rounded-md bg-amber-500 px-4 py-2 text-sm font-bold text-white">ویرایش</a>
                    <form action="{{ route('projects.destroy', $project) }}" method="POST" onsubmit="return confirm('آیا از حذف این پروژه و همه سندهای وابسته مطمئن هستید؟');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-md bg-rose-600 px-4 py-2 text-sm font-bold text-white">حذف پروژه</button>
                    </form>
                    <a href="{{ route('projects.index') }}" class="rounded-md bg-slate-500 px-4 py-2 text-sm font-bold text-white">بازگشت</a>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div class="rounded-md border border-blue-200 bg-blue-50 p-4">
                    <div class="text-sm font-bold text-blue-700">درآمد</div>
                    <div class="mt-1 text-2xl font-black text-blue-800">{{ number_format($project->total_income) }}</div>
                </div>
                <div class="rounded-md border border-red-200 bg-red-50 p-4">
                    <div class="text-sm font-bold text-red-700">هزینه</div>
                    <div class="mt-1 text-2xl font-black text-red-800">{{ number_format($project->total_expense) }}</div>
                </div>
                <div class="rounded-md border border-green-200 bg-green-50 p-4">
                    <div class="text-sm font-bold text-green-700">ساعت کار</div>
                    <div class="mt-1 text-2xl font-black text-green-800">{{ number_format($project->total_work_hours, 1) }}</div>
                </div>
                <div class="rounded-md border border-slate-200 bg-slate-50 p-4">
                    <div class="text-sm font-bold text-slate-700">وضعیت پروژه</div>
                    <div class="mt-1 text-2xl font-black text-slate-800">{{ $project->status_label }}</div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <table class="w-full border-collapse border border-gray-300 text-sm">
                    <tbody>
                    <tr><th class="border border-gray-300 bg-gray-50 p-3 text-right">شماره پروژه</th><td class="border border-gray-300 p-3">{{ $project->project_number ?: '-' }}</td></tr>
                    <tr><th class="border border-gray-300 bg-gray-50 p-3 text-right">کارفرما / مشتری</th><td class="border border-gray-300 p-3">{{ $project->party?->name ?: '-' }}</td></tr>
                    <tr><th class="border border-gray-300 bg-gray-50 p-3 text-right">مدیر پروژه</th><td class="border border-gray-300 p-3">{{ $project->manager?->name ?: '-' }}</td></tr>
                    <tr><th class="border border-gray-300 bg-gray-50 p-3 text-right">مرکز هزینه</th><td class="border border-gray-300 p-3">{{ $project->cost_center_code ?: '-' }}</td></tr>
                    </tbody>
                </table>

                <table class="w-full border-collapse border border-gray-300 text-sm">
                    <tbody>
                    <tr><th class="border border-gray-300 bg-gray-50 p-3 text-right">تاریخ شروع</th><td class="border border-gray-300 p-3">{{ $project->start_date ? verta($project->start_date)->format('Y/m/d') : '-' }}</td></tr>
                    <tr><th class="border border-gray-300 bg-gray-50 p-3 text-right">تاریخ پایان</th><td class="border border-gray-300 p-3">{{ $project->end_date ? verta($project->end_date)->format('Y/m/d') : '-' }}</td></tr>
                    <tr><th class="border border-gray-300 bg-gray-50 p-3 text-right">بودجه</th><td class="border border-gray-300 p-3">{{ number_format((float) $project->budget) }}</td></tr>
                    <tr><th class="border border-gray-300 bg-gray-50 p-3 text-right">سفارش تولید</th><td class="border border-gray-300 p-3">{{ $project->productionOrders->count() }}</td></tr>
                    </tbody>
                </table>
            </div>

            <section>
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-base font-black text-slate-800">سفارش‌های تولید پروژه</h3>
                    <a href="{{ route('production-orders.index', ['project_id' => $project->id]) }}" class="text-sm font-bold text-blue-700">مشاهده همه</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse border border-gray-300 text-sm">
                        <thead class="bg-gray-100">
                        <tr>
                            <th class="border border-gray-300 p-2">شماره</th>
                            <th class="border border-gray-300 p-2">محصول</th>
                            <th class="border border-gray-300 p-2">تعداد</th>
                            <th class="border border-gray-300 p-2">وضعیت</th>
                            <th class="border border-gray-300 p-2">عملیات</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($project->productionOrders as $order)
                            <tr>
                                <td class="border border-gray-300 p-2">{{ $order->number }}</td>
                                <td class="border border-gray-300 p-2">{{ $order->item?->name }}</td>
                                <td class="border border-gray-300 p-2">{{ number_format((float) $order->quantity, 3) }}</td>
                                <td class="border border-gray-300 p-2">{{ $order->status_label }}</td>
                                <td class="border border-gray-300 p-2"><a href="{{ route('production-orders.show', $order) }}" class="font-bold text-blue-700">جزئیات</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="border border-gray-300 p-4 text-center text-slate-500">سفارش تولیدی ثبت نشده است.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
