<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">بهای تمام‌شده پروژه</h2></x-slot>

    <div class="erp-project-detail erp-project-costing py-10">
        <div class="space-y-5 rounded-lg bg-white p-6 shadow">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-black text-slate-900">{{ $project->name }}</h2>
                    <p class="mt-1 text-sm text-slate-500">گزارش بهای تمام‌شده، بودجه و سودآوری پروژه</p>
                </div>
                <div class="flex flex-wrap gap-2 print:hidden">
                    <a href="{{ route('projects.costing.print', $project) }}" target="_blank" data-no-spa class="rounded-md bg-slate-800 px-4 py-2 text-sm font-bold text-white">چاپ گزارش</a>
                    <a href="{{ route('production-orders.create', ['project_id' => $project->id]) }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-bold text-white">سفارش تولید</a>
                    <a href="{{ route('projects.show', $project) }}" class="rounded-md bg-slate-500 px-4 py-2 text-sm font-bold text-white">بازگشت</a>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div class="rounded-md border border-blue-200 bg-blue-50 p-4"><div class="text-sm font-bold text-blue-700">درآمد پروژه</div><div class="mt-1 text-2xl font-black text-blue-800">{{ number_format($summary['revenue']) }}</div></div>
                <div class="rounded-md border border-red-200 bg-red-50 p-4"><div class="text-sm font-bold text-red-700">جمع هزینه</div><div class="mt-1 text-2xl font-black text-red-800">{{ number_format($summary['total_cost']) }}</div></div>
                <div class="rounded-md border border-emerald-200 bg-emerald-50 p-4"><div class="text-sm font-bold text-emerald-700">سود / زیان</div><div class="mt-1 text-2xl font-black text-emerald-800">{{ number_format($summary['gross_profit']) }}</div></div>
                <div class="rounded-md border border-slate-200 bg-slate-50 p-4"><div class="text-sm font-bold text-slate-700">حاشیه سود</div><div class="mt-1 text-2xl font-black text-slate-800">{{ number_format($summary['profit_margin'], 2) }}%</div></div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <table class="w-full border-collapse border border-gray-300 text-sm">
                    <tbody>
                    <tr><th class="border border-gray-300 bg-gray-50 p-3 text-right">مواد مصرفی</th><td class="border border-gray-300 p-3">{{ number_format($summary['material_cost']) }}</td></tr>
                    <tr><th class="border border-gray-300 bg-gray-50 p-3 text-right">دستمزد مستقیم</th><td class="border border-gray-300 p-3">{{ number_format($summary['labor_cost']) }}</td></tr>
                    <tr><th class="border border-gray-300 bg-gray-50 p-3 text-right">خدمات و خرید مستقیم</th><td class="border border-gray-300 p-3">{{ number_format($summary['service_cost']) }}</td></tr>
                    <tr><th class="border border-gray-300 bg-gray-50 p-3 text-right">سربار تخصیص‌یافته</th><td class="border border-gray-300 p-3">{{ number_format($summary['overhead_cost']) }}</td></tr>
                    </tbody>
                </table>

                <table class="w-full border-collapse border border-gray-300 text-sm">
                    <tbody>
                    <tr><th class="border border-gray-300 bg-gray-50 p-3 text-right">بودجه پروژه</th><td class="border border-gray-300 p-3">{{ number_format($summary['budget']) }}</td></tr>
                    <tr><th class="border border-gray-300 bg-gray-50 p-3 text-right">مانده بودجه</th><td class="border border-gray-300 p-3">{{ number_format($summary['budget_variance']) }}</td></tr>
                    <tr><th class="border border-gray-300 bg-gray-50 p-3 text-right">ساعت کار ثبت‌شده</th><td class="border border-gray-300 p-3">{{ number_format($summary['work_hours'], 2) }}</td></tr>
                    <tr><th class="border border-gray-300 bg-gray-50 p-3 text-right">مغایرت مواد</th><td class="border border-gray-300 p-3">{{ number_format($summary['material_variance']) }}</td></tr>
                    </tbody>
                </table>
            </div>

            <section class="print:hidden rounded-md border border-slate-200 bg-slate-50 p-4">
                <h3 class="mb-3 text-base font-black text-slate-800">ثبت سربار پروژه</h3>
                <form method="POST" action="{{ route('projects.overheads.store', $project) }}" class="grid grid-cols-1 gap-3 md:grid-cols-6">
                    @csrf
                    <select name="method" class="rounded-md border-slate-300">
                        <option value="manual">دستی</option>
                        <option value="labor_hours">بر اساس ساعت کار</option>
                        <option value="material_cost">بر اساس مواد</option>
                        <option value="project_value">بر اساس ارزش پروژه</option>
                        <option value="fixed_percentage">درصد ثابت</option>
                    </select>
                    <input name="base_amount" type="number" step="0.01" min="0" dir="ltr" placeholder="مبنای محاسبه" class="rounded-md border-slate-300">
                    <input name="rate" type="number" step="0.0001" min="0" dir="ltr" placeholder="نرخ" class="rounded-md border-slate-300">
                    <input name="amount" type="number" step="0.01" min="0" dir="ltr" required placeholder="مبلغ سربار (ریال)" class="rounded-md border-slate-300">
                    <input name="allocated_date" inputmode="numeric" dir="ltr" placeholder="تاریخ" value="{{ todayJalaliDate() }}" class="rounded-md border-slate-300">
                    <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-bold text-white">ثبت سربار</button>
                    <input name="description" placeholder="توضیح" class="rounded-md border-slate-300 md:col-span-6">
                </form>
            </section>

            <section>
                <h3 class="mb-3 text-base font-black text-slate-800">سفارش‌های تولید مرتبط</h3>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse border border-gray-300 text-sm">
                        <thead class="bg-gray-100">
                        <tr>
                            <th class="border border-gray-300 p-2">شماره</th>
                            <th class="border border-gray-300 p-2">محصول</th>
                            <th class="border border-gray-300 p-2">تعداد</th>
                            <th class="border border-gray-300 p-2">وضعیت</th>
                            <th class="border border-gray-300 p-2 print:hidden">عملیات</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($project->productionOrders as $order)
                            <tr>
                                <td class="border border-gray-300 p-2">{{ $order->number }}</td>
                                <td class="border border-gray-300 p-2">{{ $order->item?->name }}</td>
                                <td class="border border-gray-300 p-2">{{ number_format((float) $order->quantity, 3) }}</td>
                                <td class="border border-gray-300 p-2">{{ $order->status_label }}</td>
                                <td class="border border-gray-300 p-2 print:hidden"><a href="{{ route('production-orders.show', $order) }}" class="font-bold text-blue-700">جزئیات</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="border border-gray-300 p-5 text-center text-slate-500">سفارش تولیدی ثبت نشده است.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
