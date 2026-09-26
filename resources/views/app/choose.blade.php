<x-guest-layout portal>
    <div class="guest-portal__main">
        <div class="guest-portal__brand">
            <div class="guest-portal__logo-shell">
                <img
                    src="{{ asset('logo-aale.png') }}"
                    alt="لوگوی بیکران پایش آله"
                    class="guest-portal__logo"
                    width="88"
                    height="88"
                >
            </div>
            <h1 class="guest-portal__company">بیکران پایش آله</h1>
            <p class="guest-portal__date">{{ todayJalaliDate() }}</p>
        </div>

        <div class="guest-portal__tiles" role="navigation" aria-label="انتخاب برنامه">
            <a href="{{ route('app.switch', 'erp') }}" class="guest-portal__tile guest-portal__tile--erp">
                <span class="guest-portal__tile-mark">ERP</span>
                <span class="guest-portal__tile-sub">یکپارچه مالی</span>
            </a>

            <a href="{{ route('app.switch', 'crm') }}" class="guest-portal__tile guest-portal__tile--crm">
                <span class="guest-portal__tile-mark">CRM</span>
                <span class="guest-portal__tile-sub">مدیریت مشتریان</span>
            </a>
        </div>
    </div>

    <footer class="guest-portal__footer">
        <p>
            طراحی و پیاده‌سازی توسط شرکت بیکران پایش آله انجام شده و تمامی حقوق برنامه‌ها برای این شرکت محفوظ است.
            <span class="guest-portal__footer-year">سال ۱۴۰۵</span>
        </p>
    </footer>
</x-guest-layout>
