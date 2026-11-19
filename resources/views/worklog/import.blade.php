<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">کارکرد پرسنل</h2>
    </x-slot>

    <div class="py-12">
        <div class="bg-white rounded-lg shadow-md p-6 max-w-4xl mx-auto">
            <div class="mb-6 flex items-center justify-between gap-3">
                <h2 class="text-2xl font-bold">کارکرد پرسنل</h2>
                <a href="{{ route('work-logs.index') }}" class="erp-action-btn erp-action-detail shrink-0">
                    بازگشت
                </a>
            </div>

            @if($piofyEnabled ?? false)
                <section class="mb-6 rounded-xl border border-teal-200 bg-teal-50 p-4">
                    <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-bold text-teal-900">دریافت تردد از دستگاه</h3>
                            <p class="mt-1 text-sm text-teal-800">
                                ترددهای ثبت‌نشده از دستگاه خوانده می‌شود و با نام/شناسه پرسنل داخل برنامه ثبت می‌گردد.
                            </p>
                            <p class="mt-1 text-xs text-teal-700">
                                آدرس دستگاه: {{ $piofyBaseUrl ?? '-' }}
                                @if(! empty($lastSyncAt))
                                    — آخرین همسان‌سازی: {{ formatJalaliDateTime($lastSyncAt) }}
                                @endif
                            </p>
                        </div>
                    </div>
                    <form action="{{ route('worklog.sync-device') }}" method="POST" class="flex flex-wrap items-end gap-3">
                        @csrf
                        <label class="block w-36 text-sm font-semibold text-slate-700">
                            از تاریخ
                            <x-erp.ui.jalali-date-input name="since_date" :value="old('since_date')" placeholder="1405/01/01" class="mt-1 w-full" />
                        </label>
                        <button type="submit" class="rounded bg-teal-600 px-6 py-2 font-bold text-white hover:bg-teal-700"
                            onclick="return confirm('دریافت تردد از دستگاه شروع شود؟')">
                            دریافت تردد از دستگاه
                        </button>
                    </form>
                </section>
            @endif

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

            <section class="mb-6 rounded-xl border border-sky-200 bg-sky-50 p-4">
                <div class="mb-3">
                    <h3 class="text-base font-bold text-sky-900">دریافت تردد از اکسل</h3>
                    <p class="mt-1 text-sm text-sky-800">
                        فایل خروجی دستگاه (xlsx / csv) را آپلود کنید تا ترددها به کارکرد تبدیل شوند.
                    </p>
                </div>
                <form action="{{ route('worklog.import') }}" method="POST" enctype="multipart/form-data" class="space-y-3"
                      x-data="{ fileName: 'فایلی انتخاب نشده است' }">
                    @csrf

                    <div class="flex flex-wrap items-end gap-3">
                        <div class="w-56 shrink-0">
                            <span class="mb-1 block text-sm font-semibold text-slate-700">فایل (xlsx / csv)</span>
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-sky-200 bg-white px-2 py-1.5 text-sm text-slate-700">
                                <span class="min-w-0 flex-1 truncate" x-text="fileName"></span>
                                <span class="shrink-0 rounded bg-sky-100 px-2 py-1 text-xs font-bold text-sky-800">انتخاب فایل</span>
                                <input id="file" type="file" name="file" accept=".xlsx,.xls,.csv,.txt" required class="sr-only"
                                       @change="fileName = $event.target.files[0]?.name || 'فایلی انتخاب نشده است'">
                            </label>
                        </div>
                        <button type="submit" class="rounded bg-sky-600 px-5 py-2 font-bold text-white hover:bg-sky-700">
                            آپلود و تبدیل
                        </button>
                        <a href="{{ route('worklog.template') }}" class="rounded bg-slate-500 px-5 py-2 text-center font-bold text-white hover:bg-slate-600">
                            دانلود فایل نمونه
                        </a>
                    </div>
                    <p class="text-xs text-sky-700">
                        هدر: شناسه کاربر، نام کاربر، شماره کارت، زمان تردد (شمسی)، زمان تردد (میلادی)، کلید عملیاتی، عنوان کلید عملیاتی
                    </p>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
