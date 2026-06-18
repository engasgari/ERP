<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('مدیریت حقوق و دستمزد') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <!-- هدر صفحه -->
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold">لیست حقوق‌ها</h2>
                        <div class="flex gap-2">
                            @can('salaries.manage')
                                <a href="{{ route('salaries.bulk-delete') }}"
                                   class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition duration-200">
                                    حذف گروهی
                                </a>
                                <a href="{{ route('salaries.create') }}"
                                   class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                                    محاسبه حقوق جدید
                                </a>
                            @endcan
                            <a href="{{ route('salaries.financial-report') }}" class="block py-2 px-4 hover:bg-gray-100">
                                گزارش مالی پرسنل
                            </a>
                        </div>
                    </div>

                    <!-- نمایش پیام‌ها -->
                    @if (session('success'))
                        <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-green-400 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="text-green-800 font-semibold">{{ session('success') }}</span>
                            </div>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-red-400 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="text-red-800 font-semibold">{{ session('error') }}</span>
                            </div>
                        </div>
                    @endif

                    @if (session('warning'))
                        <div class="mb-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-yellow-400 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="text-yellow-800 font-semibold">{{ session('warning') }}</span>
                            </div>
                        </div>
                    @endif

                    <form method="GET" action="{{ route('salaries.index') }}" class="erp-ui-filter-bar">
                        <div class="erp-filter-row erp-filter-row-4">
                            <label class="erp-filter-field">
                                پرسنل
                                <select name="employee">
                                    <option value="">همه پرسنل</option>
                                    @foreach($employees as $employee)
                                        <option value="{{ $employee->id }}" @selected(request('employee') == $employee->id)>
                                            {{ $employee->full_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="erp-filter-field">
                                سال
                                <select name="year">
                                    <option value="">همه سال‌ها</option>
                                    @for($y = verta()->year; $y >= 1400; $y--)
                                        <option value="{{ $y }}" @selected(request('year') == $y)>{{ $y }}</option>
                                    @endfor
                                </select>
                            </label>

                            <label class="erp-filter-field">
                                ماه
                                <select name="month">
                                    <option value="">همه ماه‌ها</option>
                                    @for($m = 1; $m <= 12; $m++)
                                        <option value="{{ $m }}" @selected(request('month') == $m)>
                                            {{ getPersianMonthName($m) }}
                                        </option>
                                    @endfor
                                </select>
                            </label>

                            <label class="erp-filter-field">
                                وضعیت
                                <select name="status">
                                    <option value="">همه</option>
                                    <option value="draft" @selected(request('status') === 'draft')>پیش‌نویس</option>
                                    <option value="calculated" @selected(request('status') === 'calculated')>محاسبه شده</option>
                                    <option value="partial" @selected(request('status') === 'partial')>پرداخت جزئی</option>
                                    <option value="paid" @selected(request('status') === 'paid')>پرداخت شده</option>
                                </select>
                            </label>
                        </div>

                        <div class="erp-filter-row erp-filter-row-4">
                            <x-filter-actions :reset-route="route('salaries.index')" class="erp-filter-actions-full" />
                        </div>
                    </form>

                    <!-- جدول حقوق‌ها -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white border border-gray-200">
                            <thead>
                            <tr class="bg-gray-50">
                                <th class="py-3 px-4 border-b text-right font-medium text-gray-700">پرسنل</th>
                                <th class="py-3 px-4 border-b text-right font-medium text-gray-700">دوره</th>
                                <th class="py-3 px-4 border-b text-right font-medium text-gray-700">ساعت کار</th>
                                <th class="py-3 px-4 border-b text-right font-medium text-gray-700">حقوق پایه (ریال)</th>
                                <th class="py-3 px-4 border-b text-right font-medium text-gray-700">اضافه کاری</th>
                                <th class="py-3 px-4 border-b text-right font-medium text-gray-700">حقوق نهایی (ریال)</th>
                                <th class="py-3 px-4 border-b text-right font-medium text-gray-700">پرداختی</th>
                                <th class="py-3 px-4 border-b text-right font-medium text-gray-700">مانده</th>
                                <th class="py-3 px-4 border-b text-right font-medium text-gray-700">وضعیت</th>
                                <th class="py-3 px-4 border-b text-right font-medium text-gray-700">عملیات</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($salaries as $salary)
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-3 px-4">
                                        <div class="font-semibold">{{ $salary->employee->full_name }}</div>
                                        <div class="text-sm text-gray-500">{{ number_format($salary->hourly_rate) }} ریال/ساعت</div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-semibold">{{ getPersianMonthName($salary->month) }}</div>
                                        <div class="text-sm text-gray-500">{{ $salary->year }}</div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div>{{ number_format($salary->total_hours, 1) }} ساعت</div>
                                        @if($salary->overtime_hours > 0)
                                            <div class="text-sm text-orange-600">+{{ number_format($salary->overtime_hours, 1) }} اضافه</div>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4">
                                        {{ number_format($salary->base_salary) }} ریال
                                    </td>
                                    <td class="py-3 px-4">
                                        @if($salary->overtime_salary > 0)
                                            {{ number_format($salary->overtime_salary) }} ریال
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 font-semibold">
                                        {{ number_format($salary->final_salary) }} ریال
                                    </td>
                                    <td class="py-3 px-4">
                                        {{ number_format($salary->payments->sum('amount')) }} ریال
                                    </td>
                                    <td class="py-3 px-4">
                                        @if($salary->remaining_amount > 0)
                                            <span class="text-red-600 font-semibold">
                                                {{ number_format($salary->remaining_amount) }} ریال
                                            </span>
                                        @else
                                            <span class="text-green-600">تسویه شده</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4">
                                        @php
                                            $statusColors = [
                                                'draft' => 'bg-gray-100 text-gray-800',
                                                'calculated' => 'bg-blue-100 text-blue-800',
                                                'partial' => 'bg-yellow-100 text-yellow-800',
                                                'paid' => 'bg-green-100 text-green-800'
                                            ];
                                        @endphp
                                        <span class="px-2 py-1 rounded-full text-xs font-medium {{ $statusColors[$salary->status] }}">
                                            @if($salary->status == 'draft') پیش‌نویس
                                            @elseif($salary->status == 'calculated') محاسبه شده
                                            @elseif($salary->status == 'partial') پرداخت جزئی
                                            @elseif($salary->status == 'paid') پرداخت شده
                                            @endif
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="flex gap-2 flex-wrap">
                                            <a href="{{ route('salaries.show', $salary->id) }}"
                                               class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm">
                                                جزئیات
                                            </a>
                                            @can('salaries.manage')
                                                <a href="{{ route('salaries.edit', $salary->id) }}"
                                                   class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-sm">
                                                    ویرایش
                                                </a>
                                                @if($salary->remaining_amount > 0)
                                                    <a href="{{ route('salaries.show', $salary->id) }}#payment"
                                                       class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm">
                                                        پرداخت
                                                    </a>
                                                @endif
                                                <form action="{{ route('salaries.destroy', $salary->id) }}" method="POST"
                                                      class="inline" onsubmit="return confirm('آیا از حذف این محاسبه حقوق اطمینان دارید؟ این عمل غیرقابل بازگشت است.')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm">
                                                        حذف
                                                    </button>
                                                </form>
                                            @endcan
                                            <a href="{{ route('salaries.print-slip', $salary->id) }}"
                                               target="_blank"
                                               class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm">
                                                چاپ
                                            </a>
                                            @can('salaries.manage')
                                            @if($salary->remaining_amount > 0)
                                                <a href="{{ route('salaries.show', $salary->id) }}#payment"
                                                   class="bg-orange-500 hover:bg-orange-600 text-white px-3 py-1 rounded text-sm">
                                                    پرداخت
                                                </a>
                                            @endif
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="py-4 px-4 text-center text-gray-500">
                                        هیچ رکوردی یافت نشد
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- صفحه‌بندی -->
                    <div class="mt-4">
                        {{ $salaries->links() }}
                    </div>

                    <!-- اطلاعات آماری -->
                    <div class="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="text-blue-600 font-semibold">تعداد رکوردها</div>
                            <div class="text-2xl font-bold text-blue-700">{{ $stats['total'] ?? 0 }}</div>
                        </div>
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <div class="text-green-600 font-semibold">پرداخت شده</div>
                            <div class="text-2xl font-bold text-green-700">
                                {{ $stats['paid'] ?? 0 }}
                            </div>
                        </div>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <div class="text-yellow-600 font-semibold">پرداخت جزئی</div>
                            <div class="text-2xl font-bold text-yellow-700">
                                {{ $stats['partial'] ?? 0 }}
                            </div>
                        </div>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <div class="text-red-600 font-semibold">مانده پرداخت</div>
                            <div class="text-2xl font-bold text-red-700">
                                {{ $stats['pending'] ?? 0 }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal برای تایید حذف -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                    <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </div>
                <h3 class="text-lg leading-6 font-medium text-gray-900 mt-2">تایید حذف</h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500">
                        آیا از حذف این محاسبه حقوق اطمینان دارید؟ این عمل غیرقابل بازگشت است.
                    </p>
                </div>
                <div class="flex justify-center gap-3 mt-4">
                    <button id="confirmDelete" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded">
                        بله، حذف شود
                    </button>
                    <button id="cancelDelete" class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded">
                        انصراف
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

<script>
    // اسکریپت برای مدیریت Modal حذف
    let currentForm = null;

    function confirmDelete(form) {
        currentForm = form;
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    document.getElementById('cancelDelete').addEventListener('click', function() {
        document.getElementById('deleteModal').classList.add('hidden');
        currentForm = null;
    });

    document.getElementById('confirmDelete').addEventListener('click', function() {
        if (currentForm) {
            currentForm.submit();
        }
    });

    // بستن Modal با کلیک خارج از آن
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('deleteModal');
        if (event.target === modal) {
            modal.classList.add('hidden');
            currentForm = null;
        }
    });
</script>
