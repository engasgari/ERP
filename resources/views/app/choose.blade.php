<x-guest-layout>
    <div class="mb-6 text-right">
        <h1 class="text-lg font-bold text-slate-800">انتخاب برنامه</h1>
        <p class="mt-1 text-xs font-medium text-slate-500">ابتدا برنامه مورد نظر را انتخاب کنید، سپس وارد شوید.</p>
    </div>

    <div class="grid grid-cols-1 gap-4">
        <a href="{{ route('login') }}"
           class="block rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-300 hover:shadow">
            <div class="text-xs font-bold text-slate-500">ERP</div>
            <h2 class="mt-1 text-base font-bold text-slate-800">مدیریت ERP</h2>
            <p class="mt-2 text-sm text-slate-600">حسابداری، بازرگانی، منابع انسانی، انبار و گزارش‌های مدیریتی</p>
        </a>

        <a href="{{ route('crm.login') }}"
           class="block rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-300 hover:shadow">
            <div class="text-xs font-bold text-slate-500">CRM</div>
            <h2 class="mt-1 text-base font-bold text-slate-800">مدیریت ارتباط با مشتری</h2>
            <p class="mt-2 text-sm text-slate-600">مشتریان، سرنخ‌ها، فرصت‌ها، خط فروش و پیگیری فروش</p>
        </a>
    </div>
</x-guest-layout>
