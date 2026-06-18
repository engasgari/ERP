<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('تراکنش های مالی') }}
        </h2>
    </x-slot>
    <div class="py-12">
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold">تراکنش‌های مالی</h2>
            <div class="flex gap-4">
                <a href="{{ route('financial-transactions.create') }}"
                   class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded transition duration-200">
                    تراکنش جدید
                </a>
            </div>
        </div>

        <!-- خلاصه مالی -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="text-green-600 text-sm">کل درآمدها</div>
                <div class="text-2xl font-bold text-green-700">{{ number_format($summary['total_income']) }} ریال</div>
            </div>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="text-red-600 text-sm">کل هزینه‌ها</div>
                <div class="text-2xl font-bold text-red-700">{{ number_format($summary['total_expense']) }} ریال</div>
            </div>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="text-blue-600 text-sm">سود/زیان خالص</div>
                <div class="text-2xl font-bold {{ $summary['profit_loss'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
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
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" @selected(request('project_id') == $project->id)>
                                {{ $project->name }}
                            </option>
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

                <label class="erp-filter-field">
                    مبلغ از (ریال)
                    <input name="amount_min" value="{{ request('amount_min') }}" inputmode="numeric" dir="ltr" placeholder="0">
                </label>

                <label class="erp-filter-field">
                    مبلغ تا (ریال)
                    <input name="amount_max" value="{{ request('amount_max') }}" inputmode="numeric" dir="ltr" placeholder="1000000">
                </label>
            </div>

            <div class="erp-filter-row erp-filter-row-5">
                <label class="erp-filter-field">
                    از تاریخ
                    <input name="start_date" value="{{ request('start_date') ? jalaliDateInputValue(request('start_date')) : '' }}" inputmode="numeric" dir="ltr" placeholder="1403/01/01">
                </label>

                <label class="erp-filter-field">
                    تا تاریخ
                    <input name="end_date" value="{{ request('end_date') ? jalaliDateInputValue(request('end_date')) : '' }}" inputmode="numeric" dir="ltr" placeholder="1403/12/29">
                </label>

                <label class="erp-filter-field">
                    مرجع
                    <input name="reference_number" value="{{ request('reference_number') }}" placeholder="شماره مرجع">
                </label>

                <label class="erp-filter-field">
                    شرح
                    <input name="description" value="{{ request('description') }}" placeholder="جستجو در شرح">
                </label>

                <x-filter-actions :reset-route="route('financial-transactions.index')" />
            </div>
        </form>

        @foreach(array_filter($dateErrors ?? []) as $error)
            <div class="erp-filter-error">{{ $error }}</div>
        @endforeach

        @if($transactions->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full border-collapse border border-gray-300">
                    <thead class="bg-gray-200">
                    <tr>
                        <th class="border border-gray-300 p-3">تاریخ</th>
                        <th class="border border-gray-300 p-3">پروژه</th>
                        <th class="border border-gray-300 p-3">نوع</th>
                        <th class="border border-gray-300 p-3">دسته‌بندی</th>
                        <th class="border border-gray-300 p-3">مبلغ (ریال)</th>
                        <th class="border border-gray-300 p-3">شرح</th>
                        <th class="border border-gray-300 p-3">شماره مرجع</th>
                        <th class="border border-gray-300 p-3">عملیات</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($transactions as $transaction)
                        <tr class="hover:bg-gray-50">
                            <td class="border border-gray-300 p-3">
                                {{ verta($transaction->transaction_date)->format('Y/m/d') }}
                            </td>
                            <td class="border border-gray-300 p-3">
                                <a href="{{ route('financial-transactions.project-report', $transaction->project_id) }}"
                                   class="text-blue-600 hover:underline">
                                    {{ $transaction->project->name }}
                                </a>
                            </td>
                            <td class="border border-gray-300 p-3">
                            <span class="{{ $transaction->type_color }} font-semibold">
                                {{ $transaction->type_icon }} {{ $transaction->type_label }}
                            </span>
                            </td>
                            <td class="border border-gray-300 p-3">
                                {{ $transaction->category }}
                            </td>
                            <td class="border border-gray-300 p-3 font-semibold {{ $transaction->type_color }}">
                                {{ $transaction->signed_amount }}
                            </td>
                            <td class="border border-gray-300 p-3">
                                {{ $transaction->description ?: '-' }}
                            </td>
                            <td class="border border-gray-300 p-3">
                                {{ $transaction->reference_number ?: '-' }}
                            </td>
                            <td class="border border-gray-300 p-3">
                                <div class="flex gap-2">
                                    <a href="{{ route('financial-transactions.show', $transaction) }}"
                                       class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm transition duration-200">
                                        جزئیات
                                    </a>
                                    <a href="{{ route('financial-transactions.edit', $transaction) }}"
                                       class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-sm transition duration-200">
                                        ویرایش
                                    </a>
                                    <form action="{{ route('financial-transactions.destroy', $transaction) }}" method="POST" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm transition duration-200"
                                                onclick="return confirm('آیا از حذف این تراکنش مطمئن هستید؟')">
                                            حذف
                                        </button>
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
            <div class="text-center py-8">
                <p class="text-gray-500 text-lg">هنوز تراکنش مالی ثبت نکرده‌اید.</p>
                <a href="{{ route('financial-transactions.create') }}"
                   class="mt-4 inline-block bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded transition duration-200">
                    ثبت اولین تراکنش
                </a>
            </div>
        @endif
    </div>
    </div>
</x-app-layout>
