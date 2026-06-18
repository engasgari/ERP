<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('حذف گروهی محاسبات حقوق') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <!-- هدر صفحه -->
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold">حذف گروهی محاسبات حقوق</h2>
                        <a href="{{ route('salaries.index') }}"
                           class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            بازگشت به لیست
                        </a>
                    </div>

                    @if($salaries->isEmpty())
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-yellow-400 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="text-yellow-800 font-semibold">هیچ محاسبه حقوقی برای حذف وجود ندارد.</span>
                            </div>
                        </div>
                    @else
                        <form action="{{ route('salaries.destroy-multiple') }}" method="POST" id="deleteForm">
                            @csrf
                            @method('DELETE')

                            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-red-400 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-red-800 font-semibold">
                                        توجه: این عمل غیرقابل بازگشت است. تمام پرداخت‌های مرتبط با محاسبات انتخاب شده نیز حذف خواهند شد.
                                    </span>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="min-w-full bg-white border border-gray-200">
                                    <thead>
                                    <tr class="bg-gray-50">
                                        <th class="py-3 px-4 border-b text-right font-medium text-gray-700" width="50">
                                            <input type="checkbox" id="selectAll">
                                        </th>
                                        <th class="py-3 px-4 border-b text-right font-medium text-gray-700">ماه و سال</th>
                                        <th class="py-3 px-4 border-b text-right font-medium text-gray-700">تعداد پرسنل</th>
                                        <th class="py-3 px-4 border-b text-right font-medium text-gray-700">جمع حقوق (ریال)</th>
                                        <th class="py-3 px-4 border-b text-right font-medium text-gray-700">تاریخ ایجاد</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($salaries as $group => $salaryGroup)
                                        @php
                                            $firstSalary = $salaryGroup->first();
                                            $salaryIds = $salaryGroup->pluck('id');
                                            $totalSalary = $salaryGroup->sum('final_salary');
                                            $totalEmployees = $salaryGroup->count();
                                        @endphp
                                        <tr class="border-b hover:bg-gray-50">
                                            <td class="py-3 px-4">
                                                <input type="checkbox" value="{{ $group }}" class="salary-checkbox">
                                                @foreach($salaryIds as $salaryId)
                                                    <input type="hidden" name="ids[]" value="{{ $salaryId }}" class="salary-group-id" data-group="{{ $group }}" disabled>
                                                @endforeach
                                            </td>
                                            <td class="py-3 px-4">
                                                <div class="font-semibold">
                                                    {{ getPersianMonthName($firstSalary->month) }} {{ $firstSalary->year }}
                                                </div>
                                            </td>
                                            <td class="py-3 px-4">
                                                <span class="badge bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-sm">
                                                    {{ $totalEmployees }} نفر
                                                </span>
                                            </td>
                                            <td class="py-3 px-4">
                                                <span class="font-semibold">{{ number_format($totalSalary) }}</span>
                                                <span class="text-sm text-gray-500">ریال</span>
                                            </td>
                                            <td class="py-3 px-4">
                                                {{ verta($firstSalary->created_at)->format('Y/m/d H:i') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-6 flex gap-3">
                                <button type="submit"
                                        class="bg-red-500 hover:bg-red-600 text-white px-6 py-2 rounded-lg transition duration-200"
                                        onclick="return confirm('آیا از حذف محاسبات حقوق انتخاب شده اطمینان دارید؟ این عمل غیرقابل بازگشت است.')">
                                    حذف انتخاب شده‌ها
                                </button>
                                <a href="{{ route('salaries.index') }}"
                                   class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition duration-200">
                                    انصراف
                                </a>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.salary-checkbox');
            const syncHiddenIds = (checkbox) => {
                const group = checkbox.value;
                document.querySelectorAll(`.salary-group-id[data-group="${CSS.escape(group)}"]`).forEach((input) => {
                    input.disabled = !checkbox.checked;
                });
            };

            selectAll.addEventListener('change', function() {
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                    syncHiddenIds(checkbox);
                });
            });

            // اگر همه چک‌باکس‌ها انتخاب شده‌اند، selectAll را هم انتخاب کن
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    syncHiddenIds(this);
                    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                    selectAll.checked = allChecked;
                });
            });

            document.getElementById('deleteForm')?.addEventListener('submit', function(event) {
                const selected = Array.from(checkboxes).filter((checkbox) => checkbox.checked);
                if (selected.length === 0) {
                    event.preventDefault();
                    alert('حداقل یک دوره حقوق را انتخاب کنید.');
                }
            });
        });
    </script>
</x-app-layout>
