<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('ویرایش کارکرد') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h2 class="text-2xl font-bold mb-6 text-center">ویرایش کارکرد پرسنل</h2>

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

                    <form method="POST" action="{{ route('work-logs.update', $workLog->id) }}" id="workLogForm">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="employee_id" class="block text-sm font-medium text-gray-700 mb-2">پرسنل *</label>
                                <select id="employee_id" name="employee_id" required
                                       
                                       
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('employee_id') border-red-500 @enderror">
                                    <option value="">انتخاب پرسنل</option>
                                    @foreach($employees as $employee)
                                        <option value="{{ $employee->id }}"
                                                {{ old('employee_id', $workLog->employee_id) == $employee->id ? 'selected' : '' }}
                                                data-hourly-rate="{{ $employee->hourly_rate }}">
                                            {{ $employee->full_name }} ({{ number_format($employee->hourly_rate) }} ریال)
                                        </option>
                                    @endforeach
                                </select>
                                @error('employee_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="project_id" class="block text-sm font-medium text-gray-700 mb-2">پروژه</label>
                                <select id="project_id" name="project_id"
                                       
                                       
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('project_id') border-red-500 @enderror">
                                    <option value="">بدون پروژه</option>
                                    @foreach($projects as $project)
                                        <option value="{{ $project->id }}"
                                            {{ old('project_id', $workLog->project_id) == $project->id ? 'selected' : '' }}>
                                            {{ $project->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('project_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label for="work_date" class="block text-sm font-medium text-gray-700 mb-2">تاریخ کار *</label>
                                <input type="text" id="work_date" name="work_date" inputmode="numeric" dir="ltr" placeholder="1403/03/17" required
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('work_date') border-red-500 @enderror"
                                       value="{{ jalaliDateInputValue(old('work_date'), $workLog->work_date) }}">
                                @error('work_date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="start_time" class="block text-sm font-medium text-gray-700 mb-2">ساعت شروع *</label>
                                <input type="time" id="start_time" name="start_time" required
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('start_time') border-red-500 @enderror"
                                       value="{{ old('start_time', $workLog->start_time->format('H:i')) }}"
                                       onchange="calculateTotalAmount()">
                                @error('start_time')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="end_time" class="block text-sm font-medium text-gray-700 mb-2">ساعت پایان *</label>
                                <input type="time" id="end_time" name="end_time" required
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('end_time') border-red-500 @enderror"
                                       value="{{ old('end_time', $workLog->end_time->format('H:i')) }}"
                                       onchange="calculateTotalAmount()">
                                @error('end_time')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-2">شرح کار</label>
                            <textarea id="description" name="description" rows="3"
                                      class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('description') border-red-500 @enderror"
                                      placeholder="توضیحات مربوط به کار انجام شده...">{{ old('description', $workLog->description) }}</textarea>
                            @error('description')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- محاسبات -->
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4">
                            <h3 class="font-semibold mb-2">محاسبات:</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-600">ساعت کار:</span>
                                    <span id="calculated_hours" class="font-semibold">{{ number_format($workLog->hours, 1) }} ساعت</span>
                                </div>
                                <div>
                                    <span class="text-gray-600">نرخ ساعتی (ریال):</span>
                                    <span id="hourly_rate_display" class="font-semibold">{{ number_format($workLog->hourly_rate) }} ریال</span>
                                </div>
                                <div>
                                    <span class="text-gray-600">مبلغ کل (ریال):</span>
                                    <span id="total_amount_display" class="font-semibold text-green-600">{{ number_format($workLog->total_amount) }} ریال</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-4">
                            <a href="{{ route('work-logs.index') }}"
                               class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded transition duration-200">
                                انصراف
                            </a>
                            <button type="submit"
                                    class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded transition duration-200">
                                بروزرسانی کارکرد
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function calculateWorkHours() {
            const startTime = document.getElementById('start_time').value;
            const endTime = document.getElementById('end_time').value;

            if (startTime && endTime) {
                const start = new Date(`2000-01-01T${startTime}`);
                const end = new Date(`2000-01-01T${endTime}`);

                // بررسی اگر زمان پایان کوچکتر از زمان شروع باشد (عبور از نیمه شب)
                let diffMs = end - start;
                if (diffMs < 0) {
                    diffMs = (24 * 60 * 60 * 1000) + diffMs;
                }

                const diffHours = diffMs / (1000 * 60 * 60);

                document.getElementById('calculated_hours').textContent = diffHours.toFixed(1) + ' ساعت';
                return diffHours;
            }
            return 0;
        }

        function calculateTotalAmount() {
            const hours = calculateWorkHours();
            const employeeSelect = document.getElementById('employee_id');
            const selectedOption = employeeSelect.options[employeeSelect.selectedIndex];
            const hourlyRate = selectedOption ? parseFloat(selectedOption.getAttribute('data-hourly-rate')) : 0;

            if (hourlyRate) {
                document.getElementById('hourly_rate_display').textContent = hourlyRate.toLocaleString() + ' ریال';
                const totalAmount = hours * hourlyRate;
                document.getElementById('total_amount_display').textContent = totalAmount.toLocaleString() + ' ریال';
            }
        }

        // رویدادهای محاسبه
        document.getElementById('start_time').addEventListener('change', calculateTotalAmount);
        document.getElementById('end_time').addEventListener('change', calculateTotalAmount);
        document.getElementById('employee_id').addEventListener('change', calculateTotalAmount);

        // محاسبه اولیه
        calculateTotalAmount();
    </script>
</x-app-layout>

