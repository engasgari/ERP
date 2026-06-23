<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('تراکنش های مالی') }}
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-5 shadow-sm">
            <div class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-slate-800">هزینه و درآمدهای غیر فاکتوری</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-600">
                        برای ثبت اجاره، ناهار پرسنل، تنخواه و درآمدهای خاص از این بخش استفاده کنید.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('financial-transactions.summary') }}" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700 transition duration-200 hover:bg-emerald-100">
                        گزارش جمع‌بندی
                    </a>
                    <a href="{{ route('financial-transactions.create') }}" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white transition duration-200 hover:bg-blue-700">
                        ثبت تراکنش جدید
                    </a>
                </div>
            </div>

            <div class="mb-5 grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
                    <div class="text-xs font-medium uppercase tracking-wide text-emerald-600">کل درآمدها</div>
                    <div class="mt-2 text-xl font-bold text-emerald-700">{{ number_format($summary['total_income']) }} ریال</div>
                </div>

                <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 shadow-sm">
                    <div class="text-xs font-medium uppercase tracking-wide text-rose-600">کل هزینه‌ها</div>
                    <div class="mt-2 text-xl font-bold text-rose-700">{{ number_format($summary['total_expense']) }} ریال</div>
                </div>

                <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4 shadow-sm">
                    <div class="text-xs font-medium uppercase tracking-wide text-sky-600">سود / زیان خالص</div>
                    <div class="mt-2 text-xl font-bold {{ $summary['profit_loss'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                        {{ number_format($summary['profit_loss']) }} ریال
                    </div>
                </div>
            </div>

            <form method="GET" action="{{ route('financial-transactions.index') }}" class="erp-ui-filter-bar">
                <div class="erp-filter-row erp-filter-row-5">
                    <label class="erp-filter-field">
                        پروژه
                        <select name="project_id">
                            <option value="">همه</option>
                            <option value="null" @selected(request('project_id') === 'null')>بدون پروژه</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}" @selected(request('project_id') == $project->id)>{{ $project->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="erp-filter-field">
                        بانک
                        <select name="bank_account_id">
                            <option value="">همه</option>
                            @foreach($bankAccounts as $bankAccount)
                                <option value="{{ $bankAccount->id }}" @selected(request('bank_account_id') == $bankAccount->id)>{{ $bankAccount->bank_name }} - {{ $bankAccount->code }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="erp-filter-field">
                        صندوق
                        <select name="cashbox_id">
                            <option value="">همه</option>
                            @foreach($cashboxes as $cashbox)
                                <option value="{{ $cashbox->id }}" @selected(request('cashbox_id') == $cashbox->id)>{{ $cashbox->name }} - {{ $cashbox->code }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="erp-filter-field">
                        نوع
                        <select name="type">
                            <option value="">همه</option>
                            <option value="income" @selected(request('type') === 'income')>درآمد</option>
                            <option value="expense" @selected(request('type') === 'expense')>هزینه</option>
                        </select>
                    </label>

                    <label class="erp-filter-field">
                        دسته
                        <input name="category" value="{{ request('category') }}" list="financial-categories" placeholder="دسته">
                    </label>
                    <datalist id="financial-categories">
                        @foreach($categories as $category)
                            <option value="{{ $category }}"></option>
                        @endforeach
                    </datalist>
                </div>

                <div class="erp-filter-row erp-filter-row-5">
                    <label class="erp-filter-field">
                        از تاریخ
                        <input name="start_date" value="{{ request('start_date') ? jalaliDateInputValue(request('start_date')) : '' }}" inputmode="numeric" dir="ltr" placeholder="1404/01/01">
                    </label>

                    <label class="erp-filter-field">
                        تا تاریخ
                        <input name="end_date" value="{{ request('end_date') ? jalaliDateInputValue(request('end_date')) : '' }}" inputmode="numeric" dir="ltr" placeholder="1404/12/29">
                    </label>

                    <label class="erp-filter-field">
                        مبلغ از
                        <input name="amount_min" value="{{ request('amount_min') }}" inputmode="numeric" dir="ltr" placeholder="0">
                    </label>

                    <label class="erp-filter-field">
                        مبلغ تا
                        <input name="amount_max" value="{{ request('amount_max') }}" inputmode="numeric" dir="ltr" placeholder="1000000">
                    </label>

                    <label class="erp-filter-field">
                        شرح
                        <input name="description" value="{{ request('description') }}" placeholder="جستجو در شرح">
                    </label>
                </div>

                <div class="erp-filter-row erp-filter-row-5">
                    <x-filter-actions :reset-route="route('financial-transactions.index')" />
                </div>
            </form>

            @foreach(array_filter($dateErrors ?? []) as $error)
                <div class="erp-filter-error">{{ $error }}</div>
            @endforeach

            @if($transactions->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1200px] border-collapse border border-gray-300">
                        <thead class="bg-slate-100">
                        <tr>
                            <th class="border border-gray-300 px-3 py-3 text-right">تاریخ</th>
                            <th class="border border-gray-300 px-3 py-3 text-right">پروژه</th>
                            <th class="border border-gray-300 px-3 py-3 text-right">منبع</th>
                            <th class="border border-gray-300 px-3 py-3 text-right">نوع</th>
                            <th class="border border-gray-300 px-3 py-3 text-right">دسته‌بندی</th>
                            <th class="border border-gray-300 px-3 py-3 text-right">مبلغ (ریال)</th>
                            <th class="border border-gray-300 px-3 py-3 text-right">شرح</th>
                            <th class="border border-gray-300 px-3 py-3 text-right">عملیات</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($transactions as $transaction)
                            <tr class="hover:bg-slate-50">
                                <td class="border border-gray-300 px-3 py-3">{{ verta($transaction->transaction_date)->format('Y/m/d') }}</td>
                                <td class="border border-gray-300 px-3 py-3">
                                    @if($transaction->project)
                                        <a href="{{ route('financial-transactions.project-report', $transaction->project_id) }}" class="text-blue-600 hover:underline">
                                            {{ $transaction->project->name }}
                                        </a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="border border-gray-300 px-3 py-3">{{ $transaction->source_label }}</td>
                                <td class="border border-gray-300 px-3 py-3">
                                    <span class="{{ $transaction->type_color }} font-semibold">
                                        {{ $transaction->type_label }}
                                    </span>
                                </td>
                                <td class="border border-gray-300 px-3 py-3">{{ $transaction->category }}</td>
                                <td class="border border-gray-300 px-3 py-3 font-semibold {{ $transaction->type_color }}">{{ $transaction->signed_amount }}</td>
                                <td class="border border-gray-300 px-3 py-3">{{ $transaction->description ?: '-' }}</td>
                                <td class="border border-gray-300 px-3 py-3">
                                    <div class="flex flex-nowrap items-center gap-2">
                                        <a href="{{ route('financial-transactions.show', $transaction) }}" class="rounded-lg bg-blue-500 px-3 py-1 text-sm text-white transition duration-200 hover:bg-blue-600">جزئیات</a>
                                        <a href="{{ route('financial-transactions.edit', $transaction) }}" class="rounded-lg bg-amber-500 px-3 py-1 text-sm text-white transition duration-200 hover:bg-amber-600">ویرایش</a>
                                        <form action="{{ route('financial-transactions.destroy', $transaction) }}" method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg bg-red-500 px-3 py-1 text-sm text-white transition duration-200 hover:bg-red-600" onclick="return confirm('آیا از حذف این تراکنش مطمئن هستید؟')">حذف</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $transactions->links() }}
                </div>
            @else
                <div class="py-8 text-center">
                    <p class="text-lg text-gray-500">هنوز تراکنش مالی ثبت نشده است.</p>
                    <a href="{{ route('financial-transactions.create') }}" class="mt-4 inline-block rounded-xl bg-blue-600 px-6 py-2 text-white transition duration-200 hover:bg-blue-700">ثبت اولین تراکنش</a>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
