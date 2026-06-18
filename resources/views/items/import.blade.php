<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">ورود اکسل کالا و خدمات</h2>
    </x-slot>

    <div class="bg-white rounded-lg shadow-md p-6 space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">ورود گروهی کالا/خدمات</h2>
                <p class="mt-1 text-sm text-slate-500">قالب خام xlsx را دریافت کنید، اطلاعات را کامل کنید و همان فایل را اینجا بارگذاری کنید.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('items.import.template') }}" class="erp-action-btn erp-action-detail" data-no-spa>دانلود قالب خام xlsx</a>
                <a href="{{ route('items.index') }}" class="erp-action-btn erp-action-detail">بازگشت به لیست</a>
            </div>
        </div>

        @if(session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('items.import') }}" enctype="multipart/form-data" class="erp-ui-filter-bar" data-no-spa>
            @csrf
            <div class="erp-filter-row">
                <div class="erp-filter-field md:col-span-3">
                    <span>فایل اکسل</span>
                    <label class="mt-1 flex cursor-pointer items-center justify-between gap-3 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700">
                        <span id="item-import-file-name">فایلی انتخاب نشده است</span>
                        <span class="erp-action-btn erp-action-detail">انتخاب فایل</span>
                        <input id="item-import-file" type="file" name="file" accept=".xlsx,.xls,.csv,.txt" required class="sr-only">
                    </label>
                </div>
                <div class="flex items-end">
                    <button class="erp-action-btn erp-action-edit w-full">ورود اطلاعات</button>
                </div>
            </div>
            <p class="text-xs text-slate-500">فرمت پیشنهادی xlsx است. فایل‌های csv و txt هم قابل قبول هستند. اگر فایل xls قدیمی بعد از ذخیره در Excel به پوشه جداگانه وابسته شده باشد، دوباره قالب خام جدید را دانلود کنید.</p>
        </form>

        <div class="overflow-x-auto">
            <table class="erp-ui-data-table">
                <thead>
                    <tr>
                        <th>ستون</th>
                        <th>اجباری</th>
                        <th>توضیح</th>
                        <th>نمونه</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>نوع</td><td>بله</td><td>کالا یا خدمت</td><td>کالا</td></tr>
                    <tr><td>کد</td><td>خیر</td><td>اگر خالی باشد سیستم کد جدید می‌سازد.</td><td>I-00025</td></tr>
                    <tr><td>نام</td><td>بله</td><td>نام کالا یا خدمت</td><td>ورق فولادی</td></tr>
                    <tr><td>واحد</td><td>خیر</td><td>نام، کد یا شناسه واحد سنجش موجود در سیستم</td><td>کیلوگرم</td></tr>
                    <tr><td>دسته‌بندی</td><td>خیر</td><td>گروه کالا/خدمت</td><td>مواد اولیه</td></tr>
                    <tr><td>قیمت فروش (ریال)</td><td>خیر</td><td>عدد بدون واحد پول</td><td>150000</td></tr>
                    <tr><td>قیمت خرید (ریال)</td><td>خیر</td><td>عدد بدون واحد پول</td><td>120000</td></tr>
                    <tr><td>موجودی اولیه</td><td>خیر</td><td>فقط برای کالا؛ برای خدمت نادیده گرفته می‌شود.</td><td>100</td></tr>
                    <tr><td>انبار اولیه</td><td>خیر</td><td>نام یا شناسه انبار. اگر خالی باشد اولین انبار فعال انتخاب می‌شود.</td><td>انبار اصلی</td></tr>
                    <tr><td>فعال</td><td>خیر</td><td>بله/خیر یا 1/0</td><td>بله</td></tr>
                    <tr><td>توضیحات</td><td>خیر</td><td>شرح تکمیلی</td><td>نمونه کالا</td></tr>
                </tbody>
            </table>
        </div>

        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
            <div class="font-bold text-slate-800">واحدها و انبارهای قابل استفاده</div>
            <div class="mt-2 grid gap-3 md:grid-cols-2">
                <div>
                    <div class="font-semibold">واحدهای فعال</div>
                    <div class="mt-1">{{ $units->pluck('name')->join('، ') ?: 'واحد فعالی ثبت نشده است.' }}</div>
                </div>
                <div>
                    <div class="font-semibold">انبارهای فعال</div>
                    <div class="mt-1">{{ $warehouses->pluck('name')->join('، ') ?: 'انبار فعالی ثبت نشده است.' }}</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const input = document.getElementById('item-import-file');
            const label = document.getElementById('item-import-file-name');
            input?.addEventListener('change', () => {
                label.textContent = input.files?.[0]?.name || 'فایلی انتخاب نشده است';
            });
        })();
    </script>
</x-app-layout>
