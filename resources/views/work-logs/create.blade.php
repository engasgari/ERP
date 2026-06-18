<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('ثبت حضور و غیاب روزانه') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h2 class="text-2xl font-bold mb-6 text-center">ثبت حضور و غیاب</h2>

                    <!-- نمایش خطاهای عمومی -->
                    @if ($errors->any())
                        <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-red-400 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                </svg>
                                <h3 class="text-red-800 font-semibold">خطاهای زیر رخ داده است:</h3>
                            </div>
                            <ul class="mt-2 list-disc list-inside text-red-600 text-sm">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- نمایش خطاهای مربوط به آرایه employees -->
                    @if ($errors->has('employees'))
                        <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-red-400 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                </svg>
                                <h3 class="text-red-800 font-semibold">خطا در داده‌های پرسنل:</h3>
                            </div>
                            <ul class="mt-2 list-disc list-inside text-red-600 text-sm">
                                @foreach ($errors->get('employees.*') as $fieldErrors)
                                    @foreach ($fieldErrors as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- نمایش پیام موفقیت -->
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

                    <form method="POST" action="{{ route('work-logs.store') }}" id="attendanceForm">
                        @csrf

                        <!-- انتخاب تاریخ -->
                        <div class="mb-6 max-w-xs mx-auto">
                            <label for="work_date" class="block text-sm font-medium text-gray-700 mb-2">تاریخ *</label>
                            <input type="text" id="work_date" name="work_date" inputmode="numeric" dir="ltr" placeholder="1403/03/17" required
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('work_date') border-red-500 @enderror"
                                   value="{{ old('work_date') ? jalaliDateInputValue(old('work_date')) : todayJalaliDate() }}">
                            @error('work_date')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- لیست پرسنل -->
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold mb-4">لیست پرسنل</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full bg-white border border-gray-200">
                                    <thead>
                                    <tr class="bg-gray-50">
                                        <th class="py-3 px-4 border-b text-right font-medium text-gray-700">نام پرسنل</th>
                                        <th class="py-3 px-4 border-b text-right font-medium text-gray-700">نرخ ساعتی (ریال)</th>
                                        <th class="py-3 px-4 border-b text-right font-medium text-gray-700">پروژه</th>
                                        <th class="py-3 px-4 border-b text-right font-medium text-gray-700">ساعت ورود</th>
                                        <th class="py-3 px-4 border-b text-right font-medium text-gray-700">ساعت خروج</th>
                                        <th class="py-3 px-4 border-b text-right font-medium text-gray-700">توضیحات</th>
                                        <th class="py-3 px-4 border-b text-right font-medium text-gray-700">محاسبات</th>
                                        <th class="py-3 px-4 border-b text-right font-medium text-gray-700">وضعیت</th>
                                    </tr>
                                    </thead>
                                    <tbody id="employees_list">
                                    @foreach($employees as $index => $employee)
                                        <tr class="border-b employee-row" data-hourly-rate="{{ $employee->hourly_rate }}">
                                            <td class="py-3 px-4">
                                                <input type="hidden" name="employees[{{ $index }}][employee_id]" value="{{ $employee->id }}">
                                                {{ $employee->full_name }}
                                            </td>
                                            <td class="py-3 px-4">
                                                <span class="hourly-rate">{{ number_format($employee->hourly_rate) }}</span> ریال
                                            </td>
                                            <td class="py-3 px-4">
                                                <select name="employees[{{ $index }}][project_id]"
                                                        class="project-select border border-gray-300 rounded px-2 py-1 w-32 text-sm @error('employees.' . $index . '.project_id') border-red-500 @enderror">
                                                    <option value="">بدون پروژه</option>
                                                    @foreach($projects as $project)
                                                        <option value="{{ $project->id }}"
                                                            {{ old('employees.' . $index . '.project_id') == $project->id ? 'selected' : '' }}>
                                                            {{ $project->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('employees.' . $index . '.project_id')
                                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                                @enderror
                                            </td>
                                            <td class="py-3 px-4">
                                                <input type="time"
                                                       name="employees[{{ $index }}][start_time]"
                                                       class="start-time border border-gray-300 rounded px-2 py-1 w-24 @error('employees.' . $index . '.start_time') border-red-500 @enderror"
                                                       value="{{ old('employees.' . $index . '.start_time') }}"
                                                       onchange="calculateEmployeeTime(this)">
                                                @error('employees.' . $index . '.start_time')
                                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                                @enderror
                                            </td>
                                            <td class="py-3 px-4">
                                                <input type="time"
                                                       name="employees[{{ $index }}][end_time]"
                                                       class="end-time border border-gray-300 rounded px-2 py-1 w-24 @error('employees.' . $index . '.end_time') border-red-500 @enderror"
                                                       value="{{ old('employees.' . $index . '.end_time') }}"
                                                       onchange="calculateEmployeeTime(this)">
                                                @error('employees.' . $index . '.end_time')
                                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                                @enderror
                                            </td>
                                            <td class="py-3 px-4">
                                                <input type="text"
                                                       name="employees[{{ $index }}][description]"
                                                       class="description border border-gray-300 rounded px-2 py-1 w-full text-sm @error('employees.' . $index . '.description') border-red-500 @enderror"
                                                       value="{{ old('employees.' . $index . '.description') }}"
                                                       placeholder="توضیحات اختیاری">
                                                @error('employees.' . $index . '.description')
                                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                                @enderror
                                            </td>
                                            <td class="py-3 px-4">
                                                <div class="text-xs">
                                                    <div>ساعت کار: <span class="work-hours">0</span></div>
                                                    <div>مبلغ: <span class="total-amount">0</span> ریال</div>
                                                </div>
                                            </td>
                                            <td class="py-3 px-4">
                                                <span class="status-badge bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded">عدم حضور</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- جمع کل -->
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                            <h3 class="font-semibold mb-2 text-blue-800">جمع کل روز:</h3>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                                <div>
                                    <span class="text-blue-600">تعداد پرسنل:</span>
                                    <span id="total_employees" class="font-semibold">{{ count($employees) }} نفر</span>
                                </div>
                                <div>
                                    <span class="text-blue-600">پرسنل حاضر:</span>
                                    <span id="present_employees" class="font-semibold">0 نفر</span>
                                </div>
                                <div>
                                    <span class="text-blue-600">مجموع ساعت‌کار:</span>
                                    <span id="total_hours" class="font-semibold">0 ساعت</span>
                                </div>
                                <div>
                                    <span class="text-blue-600">مجموع مبلغ:</span>
                                    <span id="total_amount" class="font-semibold text-green-600">0 ریال</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-4 justify-center">
                            <a href="{{ route('work-logs.index') }}"
                               class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded transition duration-200">
                                انصراف
                            </a>
                            <button type="submit"
                                    class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded transition duration-200">
                                ثبت حضور و غیاب
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // محاسبه زمان و مبلغ برای هر پرسنل
        function calculateEmployeeTime(input) {
            const row = input.closest('.employee-row');
            const startTime = row.querySelector('.start-time').value;
            const endTime = row.querySelector('.end-time').value;
            const hourlyRate = parseFloat(row.getAttribute('data-hourly-rate'));
            const workHoursSpan = row.querySelector('.work-hours');
            const totalAmountSpan = row.querySelector('.total-amount');
            const statusBadge = row.querySelector('.status-badge');

            if (startTime && endTime) {
                const start = new Date(`2000-01-01T${startTime}`);
                const end = new Date(`2000-01-01T${endTime}`);

                // بررسی اگر زمان پایان کوچکتر از زمان شروع باشد (عبور از نیمه شب)
                let diffMs = end - start;
                if (diffMs < 0) {
                    diffMs = (24 * 60 * 60 * 1000) + diffMs;
                }

                const diffHours = diffMs / (1000 * 60 * 60);
                const totalAmount = diffHours * hourlyRate;

                workHoursSpan.textContent = diffHours.toFixed(1);
                totalAmountSpan.textContent = totalAmount.toLocaleString();

                // تغییر وضعیت به حاضر
                statusBadge.textContent = 'حاضر';
                statusBadge.className = 'status-badge bg-green-100 text-green-800 text-xs px-2 py-1 rounded';
            } else {
                workHoursSpan.textContent = '0';
                totalAmountSpan.textContent = '0';

                // تغییر وضعیت به عدم حضور
                statusBadge.textContent = 'عدم حضور';
                statusBadge.className = 'status-badge bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded';
            }

            // محاسبه جمع کل
            calculateTotals();
        }

        // محاسبه جمع کل تمام پرسنل
        function calculateTotals() {
            let totalHours = 0;
            let totalAmount = 0;
            let presentCount = 0;

            document.querySelectorAll('.employee-row').forEach(row => {
                const workHours = parseFloat(row.querySelector('.work-hours').textContent) || 0;
                const hourlyRate = parseFloat(row.getAttribute('data-hourly-rate'));
                const startTime = row.querySelector('.start-time').value;
                const endTime = row.querySelector('.end-time').value;

                if (startTime && endTime) {
                    totalHours += workHours;
                    totalAmount += workHours * hourlyRate;
                    presentCount++;
                }
            });

            document.getElementById('total_hours').textContent = totalHours.toFixed(1) + ' ساعت';
            document.getElementById('total_amount').textContent = totalAmount.toLocaleString() + ' ریال';
            document.getElementById('present_employees').textContent = presentCount + ' نفر';
        }

        // تنظیم تاریخ امروز به صورت پیش‌فرض
        if (!document.getElementById('work_date').value) {
            document.getElementById('work_date').value = '{{ todayJalaliDate() }}';
        }

        // محاسبه اولیه
        calculateTotals();

        // محاسبه مجدد برای فیلدهایی که از قبل مقدار دارند (در صورت بازگشت با خطا)
        document.querySelectorAll('.start-time, .end-time').forEach(input => {
            if (input.value) {
                calculateEmployeeTime(input);
            }
        });
    </script>
</x-app-layout>
