<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">ویرایش حقوق</h2>
    </x-slot>

    <div class="py-12">
        <div class="bg-white rounded-lg shadow-md p-6 max-w-4xl mx-auto">
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-2xl font-bold">ویرایش محاسبه حقوق</h2>
                <a href="{{ route('salaries.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">بازگشت</a>
            </div>

            <form method="POST" action="{{ route('salaries.update', $salary) }}">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">پرسنل *</label>
                        <select name="employee_id" required class="w-full border border-gray-300 rounded-md px-3 py-2">
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" @selected(old('employee_id', $salary->employee_id) == $employee->id)>{{ $employee->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">سال *</label>
                        <select name="year" required class="w-full border border-gray-300 rounded-md px-3 py-2">
                            @for($year = (int) now()->year + 1; $year >= 1400; $year--)
                                <option value="{{ $year }}" @selected(old('year', $salary->year) == $year)>{{ toPersianDigits($year) }}</option>
                            @endfor
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">ماه *</label>
                        <select name="month" required class="w-full border border-gray-300 rounded-md px-3 py-2">
                            @for($month = 1; $month <= 12; $month++)
                                <option value="{{ $month }}" @selected(old('month', $salary->month) == $month)>{{ getPersianMonthName($month) }}</option>
                            @endfor
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">ساعت کار *</label>
                        <input type="number" step="0.01" name="total_hours" value="{{ old('total_hours', $salary->total_hours) }}" required class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">نرخ ساعتی (ریال) *</label>
                        <input type="number" step="1000" name="hourly_rate" value="{{ old('hourly_rate', $salary->hourly_rate) }}" required class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">حقوق پایه (ریال) *</label>
                        <input type="number" step="1000" name="base_salary" value="{{ old('base_salary', $salary->base_salary) }}" required class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">ساعت اضافه کاری</label>
                        <input type="number" step="0.01" name="overtime_hours" value="{{ old('overtime_hours', $salary->overtime_hours) }}" class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">نرخ اضافه کاری (ریال)</label>
                        <input type="number" step="1000" name="overtime_rate" value="{{ old('overtime_rate', $salary->overtime_rate) }}" class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">مبلغ اضافه کاری (ریال)</label>
                        <input type="number" step="1000" name="overtime_salary" value="{{ old('overtime_salary', $salary->overtime_salary) }}" class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">پاداش</label>
                        <input type="number" step="1000" name="bonus" value="{{ old('bonus', $salary->bonus) }}" class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">کسورات</label>
                        <input type="number" step="1000" name="deduction" value="{{ old('deduction', $salary->deduction) }}" class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">تنخواه</label>
                        <input type="number" step="1000" name="advance_payment" value="{{ old('advance_payment', $salary->advance_payment) }}" class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">وضعیت</label>
                        <select name="status" required class="w-full border border-gray-300 rounded-md px-3 py-2">
                            <option value="draft" @selected(old('status', $salary->status) === 'draft')>پیش نویس</option>
                            <option value="calculated" @selected(old('status', $salary->status) === 'calculated')>محاسبه شده</option>
                            <option value="partial" @selected(old('status', $salary->status) === 'partial')>پرداخت جزئی</option>
                            <option value="paid" @selected(old('status', $salary->status) === 'paid')>پرداخت شده</option>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">یادداشت</label>
                    <textarea name="notes" rows="3" class="w-full border border-gray-300 rounded-md px-3 py-2">{{ old('notes', $salary->notes) }}</textarea>
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded">ذخیره تغییرات</button>
                    <a href="{{ route('salaries.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded">انصراف</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

