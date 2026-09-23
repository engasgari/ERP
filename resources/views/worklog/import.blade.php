<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">ورود کارکرد از فایل دستگاه تردد</h2>
    </x-slot>

    <div class="py-12">
        <div class="bg-white rounded-lg shadow-md p-6 max-w-4xl mx-auto">
            <div class="mb-6 flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold">ورود اکسل کارکرد (Piofy)</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        خروجی ماهانه دستگاه تردد (xlsx یا csv) را آپلود کنید. هر جفت ورود/خروج یک رکورد کارکرد می‌سازد و پروژه خالی می‌ماند تا در لیست کارکرد انتخاب شود.
                    </p>
                </div>
                <a href="{{ route('work-logs.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                    بازگشت
                </a>
            </div>

            @if (session('success'))
                <div class="mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
            @endif

            @if (session('error'))
                <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form action="{{ route('worklog.import') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
                @csrf

                <div>
                    <label for="file" class="block text-sm font-semibold text-slate-700 mb-2">فایل خروجی دستگاه (xlsx / csv)</label>
                    <input type="file" id="file" name="file" accept=".xlsx,.xls,.csv,.txt" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <p class="mt-2 text-xs text-slate-500">
                        هدر Piofy: شناسه کاربر، نام کاربر، شماره کارت، زمان تردد (شمسی)، زمان تردد (میلادی)، کلید عملیاتی، عنوان کلید عملیاتی
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded">
                        آپلود و تبدیل
                    </button>
                    <a href="{{ route('worklog.template') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded text-center">
                        دانلود فایل نمونه
                    </a>
                    <a href="{{ route('worklog.export') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded text-center">
                        خروجی کارکردها
                    </a>
                </div>
            </form>

            <div class="mt-6 rounded-lg bg-gray-50 p-4 text-sm text-slate-700">
                <h3 class="mb-2 font-bold">نحوه تبدیل</h3>
                <ul class="list-disc space-y-2 pr-5">
                    <li>پرسنل با شماره کارت، شناسه کاربر، کد پرسنلی یا نام پیدا می‌شود.</li>
                    <li>بعد از ورود، پروژه همه رکوردها خالی است؛ در لیست کارکرد پروژه را انتخاب کنید.</li>
                    <li>برای هر پرسنل در هر روز، اولین و آخرین تردد به عنوان ورود و خروج ثبت می‌شود.</li>
                    <li>فایل خروجی مستقیم دستگاه Piofy با پسوند xlsx قابل آپلود است.</li>
                </ul>
            </div>

            <div class="mt-6 rounded-lg bg-gray-50 p-4 text-sm text-slate-700">
                <h3 class="mb-2 font-bold">نمونه ستون‌های دستگاه</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                        <tr>
                            <th>شناسه کاربر</th>
                            <th>نام کاربر</th>
                            <th>شماره کارت</th>
                            <th>زمان تردد (شمسی)</th>
                            <th>زمان تردد (میلادی)</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr>
                            <td>1</td>
                            <td>Employee Name</td>
                            <td>1001</td>
                            <td>1405/06/25 08:00:00</td>
                            <td>2026/09/16 08:00:00</td>
                        </tr>
                        <tr>
                            <td>1</td>
                            <td>Employee Name</td>
                            <td>1001</td>
                            <td>1405/06/25 16:00:00</td>
                            <td>2026/09/16 16:00:00</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
