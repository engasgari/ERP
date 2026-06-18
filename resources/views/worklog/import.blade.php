<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">ورود کارکرد از فایل دستگاه تردد</h2>
    </x-slot>

    <div class="py-12">
        <div class="bg-white rounded-lg shadow-md p-6 max-w-4xl mx-auto">
            <div class="mb-6 flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold">Import Attendance</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        این صفحه فقط فایل نمونه حضور و غیاب را می‌پذیرد. فایل CSV را آپلود کنید تا هر جفت تردد ورود و خروج به یک رکورد در جدول <code>work_logs</code> تبدیل شود.
                    </p>
                </div>
                <a href="{{ route('work-logs.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                    بازگشت
                </a>
            </div>

            @if(session('success'))
                <div class="mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-800">
                    {{ session('error') }}
                </div>
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
                    <label for="file" class="block text-sm font-semibold text-slate-700 mb-2">فایل CSV دستگاه تردد</label>
                    <input type="file" id="file" name="file" accept=".csv,.txt" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <p class="mt-2 text-xs text-slate-500">
                        هدر صحیح: user_id, employee_code, user_name, project_id, project_name, jalali_datetime, gregorian_datetime
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
                    <li>اگر ستون <code>employee_code</code> یا <code>user_id</code> وجود داشته باشد، پرسنل پیدا می‌شود.</li>
                    <li>اگر <code>project_id</code> معتبر نباشد، سیستم از پروژه پیش‌فرض استفاده می‌کند.</li>
                    <li>اگر فقط یک فایل تردد با ورود و خروج داشته باشید، از اولین و آخرین زمان همان روز یک کارکرد ساخته می‌شود.</li>
                    <li>این صفحه برای import دستی کارکرد نیست و نیازی به <code>work_date</code> ندارد.</li>
                </ul>
            </div>

            <div class="mt-6 rounded-lg bg-gray-50 p-4 text-sm text-slate-700">
                <h3 class="mb-2 font-bold">نمونه فایل</h3>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                        <tr>
                            <th>user_id</th>
                            <th>employee_code</th>
                            <th>user_name</th>
                            <th>project_id</th>
                            <th>project_name</th>
                            <th>jalali_datetime</th>
                            <th>gregorian_datetime</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr>
                            <td>1</td>
                            <td>EMP-00001</td>
                            <td>Employee Name</td>
                            <td>1</td>
                            <td>اداری - داخل سازمانی</td>
                            <td>1403/03/17 08:00</td>
                            <td>2024-06-06 08:00</td>
                        </tr>
                        <tr>
                            <td>1</td>
                            <td>EMP-00001</td>
                            <td>Employee Name</td>
                            <td>1</td>
                            <td>اداری - داخل سازمانی</td>
                            <td>1403/03/17 16:00</td>
                            <td>2024-06-06 16:00</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
