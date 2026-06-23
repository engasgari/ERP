<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('محاسبه حقوق جدید') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h2 class="text-2xl font-bold mb-6 text-center">محاسبه حقوق پرسنل</h2>

                    <!-- نمایش خطاها -->
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

                    <form method="POST" action="{{ route('salaries.calculate') }}">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            <div>
                                <label for="year" class="block text-sm font-medium text-gray-700 mb-2">سال شمسی *</label>
                                <select id="year" name="year" required
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    @for($y = $currentYear; $y >= 1400; $y--)
                                        <option value="{{ $y }}" {{ $currentYear == $y ? 'selected' : '' }}>{{ toPersianDigits($y) }}</option>
                                    @endfor
                                </select>
                            </div>

                            <div>
                                <label for="month" class="block text-sm font-medium text-gray-700 mb-2">ماه شمسی *</label>
                                <select id="month" name="month" required
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    @php
                                        $persianMonths = [
                                            1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر',
                                            5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان',
                                            9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'
                                        ];
                                    @endphp
                                    @foreach($persianMonths as $key => $name)
                                        <option value="{{ $key }}" {{ $currentMonth == $key ? 'selected' : '' }}>
                                            {{ $name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="overtime_rate_multiplier" class="block text-sm font-medium text-gray-700 mb-2">
                                    ضریب اضافه کاری *
                                </label>
                                <input type="number" id="overtime_rate_multiplier" name="overtime_rate_multiplier"
                                       step="0.1" min="1" max="3" value="1.5" required
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="مثلاً 1.5">
                                <p class="text-xs text-gray-500 mt-1">نرخ اضافه کاری (ریال) = نرخ ساعتی × این ضریب</p>
                            </div>
                        </div>

                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                            <h3 class="font-semibold text-blue-800 mb-2">راهنما:</h3>
                            <ul class="text-sm text-blue-700 list-disc list-inside space-y-1">
                                <li>سیستم به طور خودکار کارکردهای ماه انتخاب شده را محاسبه می‌کند</li>
                                <li>ساعات کاری بیشتر از ۸ ساعت در روز به عنوان اضافه کاری محاسبه می‌شود</li>
                                <li>پس از محاسبه می‌توانید پاداش، کسورات و تنخواه را تنظیم کنید</li>
                            </ul>
                        </div>

                        <div class="flex gap-4 justify-center">
                            <a href="{{ route('salaries.index') }}"
                               class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded transition duration-200">
                                انصراف
                            </a>
                            <button type="submit"
                                    class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded transition duration-200">
                                محاسبه حقوق‌ها
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
