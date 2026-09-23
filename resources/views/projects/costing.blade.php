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
                <div class="rounded-md border border-blue-200 bg-blue-50 p-4">
                    <div class="text-sm font-bold text-blue-700">درآمد خالص پروژه</div>
                    <div class="mt-1 text-2xl font-black text-blue-800">{{ formatMoney($summary['revenue']) }}</div>
                    <div class="mt-2 space-y-1 text-xs leading-5 text-blue-700">
                        <div>بدون ارزش افزوده</div>
                        <div class="font-semibold text-blue-900">با ارزش افزوده: {{ formatMoney($summary['gross_revenue']) }}</div>
                    </div>
                </div>
                <div class="rounded-md border border-red-200 bg-red-50 p-4">
                    <div class="text-sm font-bold text-red-700">جمع هزینه</div>
                    <div class="mt-1 text-2xl font-black text-red-800">{{ formatMoney($summary['total_cost_net']) }}</div>
                    <div class="mt-2 space-y-1 text-xs leading-5 text-red-700">
                        <div>بدون ارزش افزوده</div>
                        <div class="font-semibold text-red-900">با ارزش افزوده: {{ formatMoney($summary['total_cost']) }}</div>
                    </div>
                </div>
                @php($profitNet = (float) ($summary['profit_net'] ?? $summary['gross_profit']))
                @php($profitGross = (float) ($summary['profit_gross'] ?? $profitNet))
                <div class="rounded-md border border-emerald-200 bg-emerald-50 p-4">
                    <div class="text-sm font-bold text-emerald-700">سود / زیان</div>
                    <div class="mt-2 space-y-2 text-sm">
                        <div>
                            <div class="text-xs font-semibold text-emerald-700">خالص بدون ارزش افزوده</div>
                            <div class="text-xl font-black {{ $profitNet >= 0 ? 'text-emerald-800' : 'text-red-700' }}">{{ formatMoney($profitNet) }}</div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold text-emerald-700">با ارزش افزوده</div>
                            <div class="text-xl font-black {{ $profitGross >= 0 ? 'text-emerald-800' : 'text-red-700' }}">{{ formatMoney($profitGross) }}</div>
                        </div>
                    </div>
                </div>
                <div class="rounded-md border border-slate-200 bg-slate-50 p-4">
                    <div class="text-sm font-bold text-slate-700">حاشیه سود</div>
                    <div class="mt-1 text-2xl font-black text-slate-800">{{ formatMoney($summary['profit_margin'], 2) }}%</div>
                    <div class="mt-2 text-xs leading-5 text-slate-600">بر اساس درآمد و سود خالص (بدون ارزش افزوده)</div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <table class="w-full border-collapse border border-gray-300 text-sm">
                    <tbody>
                    <tr><th class="border border-gray-300 bg-gray-50 p-3 text-right">مواد مصرفی</th><td class="border border-gray-300 p-3">{{ formatMoney($summary['material_cost']) }}</td></tr>
                    <tr><th class="border border-gray-300 bg-gray-50 p-3 text-right">دستمزد مستقیم</th><td class="border border-gray-300 p-3">{{ formatMoney($summary['labor_cost']) }}</td></tr>
                    <tr><th class="border border-gray-300 bg-gray-50 p-3 text-right">خدمات و خرید مستقیم</th><td class="border border-gray-300 p-3"><div>{{ formatMoney($summary['service_cost_net']) }}</div><div class="mt-1 text-xs text-slate-500">با ارزش افزوده: {{ formatMoney($summary['service_cost']) }}</div></td></tr>
                    <tr><th class="border border-gray-300 bg-gray-50 p-3 text-right">هزینه‌های ثبت‌شده پروژه</th><td class="border border-gray-300 p-3">{{ formatMoney($summary['registered_expense_cost']) }}</td></tr>
                    <tr><th class="border border-gray-300 bg-gray-50 p-3 text-right">هزینه‌های حسابداری پروژه</th><td class="border border-gray-300 p-3">{{ formatMoney($summary['ledger_expense_cost']) }}</td></tr>
                    </tbody>
                </table>

                <table class="w-full border-collapse border border-gray-300 text-sm">
                    <tbody>
                    <tr><th class="border border-gray-300 bg-gray-50 p-3 text-right">درآمد فاکتور فروش (ناخالص)</th><td class="border border-gray-300 p-3">{{ formatMoney($summary['invoice_gross_revenue']) }}</td></tr>
                    <tr><th class="border border-gray-300 bg-gray-50 p-3 text-right">مالیات ارزش افزوده دریافتی</th><td class="border border-gray-300 p-3 text-amber-700">-{{ formatMoney($summary['vat_collected']) }}</td></tr>
                    @if(($summary['financial_revenue'] ?? 0) > 0)
                    <tr><th class="border border-gray-300 bg-gray-50 p-3 text-right">درآمد مالی متفرقه</th><td class="border border-gray-300 p-3">{{ formatMoney($summary['financial_revenue']) }}</td></tr>
                    @endif
                    <tr><th class="border border-gray-300 bg-gray-50 p-3 text-right">بودجه پروژه</th><td class="border border-gray-300 p-3">{{ formatMoney($summary['budget']) }}</td></tr>
                    <tr><th class="border border-gray-300 bg-gray-50 p-3 text-right">مانده بودجه</th><td class="border border-gray-300 p-3">{{ formatMoney($summary['budget_variance']) }}</td></tr>
                    <tr><th class="border border-gray-300 bg-gray-50 p-3 text-right">ساعت کار ثبت‌شده</th><td class="border border-gray-300 p-3">{{ formatMoney($summary['work_hours'], 2) }}</td></tr>
                    <tr><th class="border border-gray-300 bg-gray-50 p-3 text-right">مغایرت مواد</th><td class="border border-gray-300 p-3">{{ formatMoney($summary['material_variance']) }}</td></tr>
                    </tbody>
                </table>
            </div>

            <section>
                <h3 class="mb-3 text-base font-black text-slate-800">فاکتورهای فروش پروژه</h3>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse border border-gray-300 text-sm">
                        <thead class="bg-gray-100">
                        <tr>
                            <th class="border border-gray-300 p-2">شماره</th>
                            <th class="border border-gray-300 p-2">طرف حساب</th>
                            <th class="border border-gray-300 p-2">تاریخ</th>
                            <th class="border border-gray-300 p-2">مبلغ</th>
                            <th class="border border-gray-300 p-2">وضعیت</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($saleInvoices as $invoice)
                            <tr>
                                <td class="border border-gray-300 p-2">{{ $invoice->number }}</td>
                                <td class="border border-gray-300 p-2">{{ $invoice->party?->name ?: '-' }}</td>
                                <td class="border border-gray-300 p-2">{{ gregorianToJalaliDate($invoice->invoice_date) }}</td>
                                <td class="border border-gray-300 p-2">{{ formatMoney((float) $invoice->total_amount) }}</td>
                                <td class="border border-gray-300 p-2">{{ $invoice->settlement_status_label }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="border border-gray-300 p-5 text-center text-slate-500">فاکتور فروش متصل به این پروژه ثبت نشده است.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section>
                <h3 class="mb-3 text-base font-black text-slate-800">فاکتورهای خرید پروژه</h3>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse border border-gray-300 text-sm">
                        <thead class="bg-gray-100">
                        <tr>
                            <th class="border border-gray-300 p-2">شماره</th>
                            <th class="border border-gray-300 p-2">طرف حساب</th>
                            <th class="border border-gray-300 p-2">تاریخ</th>
                            <th class="border border-gray-300 p-2">مبلغ</th>
                            <th class="border border-gray-300 p-2">وضعیت</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($purchaseInvoices as $invoice)
                            <tr>
                                <td class="border border-gray-300 p-2">{{ $invoice->number }}</td>
                                <td class="border border-gray-300 p-2">{{ $invoice->party?->name ?: '-' }}</td>
                                <td class="border border-gray-300 p-2">{{ gregorianToJalaliDate($invoice->invoice_date) }}</td>
                                <td class="border border-gray-300 p-2">{{ formatMoney((float) $invoice->total_amount) }}</td>
                                <td class="border border-gray-300 p-2">{{ $invoice->settlement_status_label }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="border border-gray-300 p-5 text-center text-slate-500">فاکتور خرید متصل به این پروژه ثبت نشده است.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section>
                <h3 class="mb-3 text-base font-black text-slate-800">هزینه‌های ثبت‌شده پروژه</h3>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse border border-gray-300 text-sm">
                        <thead class="bg-gray-100">
                        <tr>
                            <th class="border border-gray-300 p-2">تاریخ</th>
                            <th class="border border-gray-300 p-2">دسته</th>
                            <th class="border border-gray-300 p-2">حساب / تفصیل</th>
                            <th class="border border-gray-300 p-2">منبع پرداخت</th>
                            <th class="border border-gray-300 p-2">شرح</th>
                            <th class="border border-gray-300 p-2">مبلغ</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($registeredExpenses as $expense)
                            <tr>
                                <td class="border border-gray-300 p-2">{{ gregorianToJalaliDate($expense->transaction_date) }}</td>
                                <td class="border border-gray-300 p-2">{{ $expense->category ?: '-' }}</td>
                                <td class="border border-gray-300 p-2">{{ $expense->coding_label }}</td>
                                <td class="border border-gray-300 p-2">{{ $expense->source_label }}</td>
                                <td class="border border-gray-300 p-2">{{ $expense->description ?: '-' }}</td>
                                <td class="border border-gray-300 p-2">{{ formatMoney((float) $expense->amount) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="border border-gray-300 p-5 text-center text-slate-500">هزینه ثبت‌شده‌ای برای این پروژه وجود ندارد.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
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
                                <td class="border border-gray-300 p-2">{{ formatQuantity((float) $order->quantity) }}</td>
                                <td class="border border-gray-300 p-2">{{ $order->status_label }}</td>
                                <td class="border border-gray-300 p-2 print:hidden"><a href="{{ route('production-orders.show', $order) }}" class="font-bold text-blue-700">جزئیات</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="border border-gray-300 p-5 text-center text-slate-500">سفارشی تولیدی ثبت نشده است.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
