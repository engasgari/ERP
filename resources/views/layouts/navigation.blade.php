@php
    $navLinks = [
        ['label' => 'سلف‌سرویس', 'route' => 'self-service.index', 'active' => 'self-service.*'],
        ['label' => 'داشبورد', 'route' => 'dashboard', 'active' => 'dashboard'],
        [
            'label' => 'اطلاعات پایه',
            'route' => 'parties.index',
            'active' => ['parties.*', 'chart-accounts.*', 'company-settings.*'],
            'children' => [
                ['label' => 'اشخاص و شرکت‌ها', 'route' => 'parties.index'],
                ['label' => 'تعریف شخص/شرکت', 'route' => 'parties.create'],
                ['label' => 'کدینگ مالی', 'route' => 'chart-accounts.index'],
                ['label' => 'تنظیمات شرکت', 'route' => 'company-settings.edit'],
            ],
        ],
        [
            'label' => 'بازرگانی',
            'route' => 'invoices.index',
            'active' => 'invoices.*',
            'children' => [
                ['label' => 'پیش‌فاکتور فروش', 'route' => 'invoices.create', 'params' => ['direction' => 'sale', 'document_type' => 'proforma']],
                ['label' => 'فاکتور فروش', 'route' => 'invoices.create', 'params' => ['direction' => 'sale', 'document_type' => 'invoice']],
                ['label' => 'فاکتور خرید', 'route' => 'invoices.create', 'params' => ['direction' => 'purchase', 'document_type' => 'invoice']],
                ['label' => 'لیست فاکتورها', 'route' => 'invoices.index'],
            ],
        ],
        [
            'label' => 'پروژه‌ها',
            'route' => 'projects.index',
            'active' => ['projects.*', 'production-orders.*', 'project-boms.*'],
            'children' => [
                ['label' => 'لیست پروژه‌ها', 'route' => 'projects.index'],
                ['label' => 'تعریف پروژه', 'route' => 'projects.create'],
                ['label' => 'سفارش‌های تولید', 'route' => 'production-orders.index'],
                ['label' => 'فرمول ساخت BOM', 'route' => 'project-boms.index'],
            ],
        ],
        [
            'label' => 'منابع انسانی',
            'route' => 'employees.index',
            'active' => ['employees.*', 'organization-units.*', 'jobs.*', 'positions.*', 'employment-orders.*', 'employment-contracts.*', 'employee-documents.*', 'work-logs.*', 'work-shifts.*', 'work-calendars.*', 'work-groups.*', 'attendance.*', 'salaries.*', 'payroll.*'],
            'children' => [
                ['label' => 'لیست پرسنل', 'route' => 'employees.index'],
                ['label' => 'ایجاد کارمند', 'route' => 'employees.create'],
                ['label' => 'مدارک پرسنلی', 'route' => 'employee-documents.index'],
                ['type' => 'divider'],
                ['label' => 'ساختار سازمانی', 'route' => 'organization-units.index'],
                ['label' => 'مشاغل', 'route' => 'jobs.index'],
                ['label' => 'پست‌های سازمانی', 'route' => 'positions.index'],
                ['type' => 'divider'],
                ['label' => 'احکام کارگزینی', 'route' => 'employment-orders.index'],
                ['label' => 'قراردادها', 'route' => 'employment-contracts.index'],
                ['type' => 'divider'],
                ['label' => 'شیفت‌های کاری', 'route' => 'work-shifts.index'],
                ['label' => 'تقویم کاری', 'route' => 'work-calendars.index'],
                ['label' => 'گروه‌های کاری', 'route' => 'work-groups.index'],
            ],
        ],
          [
            'label' => 'حقوق و دستمزد',
            'route' => 'employees.index',
            'active' => ['employees.*', 'organization-units.*', 'jobs.*', 'positions.*', 'employment-orders.*', 'employment-contracts.*', 'employee-documents.*', 'work-logs.*', 'work-shifts.*', 'work-calendars.*', 'work-groups.*', 'attendance.*', 'salaries.*', 'payroll.*'],
            'children' => [
                ['label' => 'لیست کارکرد', 'route' => 'work-logs.index'],
                ['label' => 'محاسبه کارکرد', 'route' => 'attendance.calculations'],
                ['label' => 'خلاصه کارکرد ماهانه', 'route' => 'attendance.summaries'],
                ['label' => 'درخواست‌های مرخصی', 'route' => 'attendance.leaves'],
                ['label' => 'درخواست‌های ماموریت', 'route' => 'attendance.missions'],
                ['label' => 'تخصیص گروه کاری', 'route' => 'work-groups.assignments'],
                ['type' => 'divider'],
                ['label' => 'دوره‌های حقوق و دستمزد', 'route' => 'payroll.periods.index'],
                ['label' => 'لیست حقوق', 'route' => 'salaries.index'],
                ['label' => 'تنظیمات حسابداری حقوق', 'route' => 'payroll.accounting-settings.index'],
            ],
        ],
        [
            'label' => 'انبار',
            'route' => 'inventory-documents.index',
            'active' => ['items.*', 'warehouses.*', 'inventory-documents.*', 'management-reports.warehouse-*'],
            'children' => [
                ['label' => 'کالا و خدمات', 'route' => 'items.index'],
                ['label' => 'تعریف کالا/خدمت', 'route' => 'items.create'],
                ['label' => 'لیست انبارها', 'route' => 'warehouses.index'],
                ['label' => 'تعریف انبار', 'route' => 'warehouses.create'],
                ['label' => 'اسناد انبار', 'route' => 'inventory-documents.index'],
                ['label' => 'موجودی انبار', 'route' => 'management-reports.warehouse-inventory'],
                ['label' => 'کاردکس انبار', 'route' => 'management-reports.warehouse-cardex'],
            ],
        ],
        [
            'label' => 'مالی',
            'route' => 'accounting-documents.index',
            'active' => ['accounting-documents.*', 'financial-transactions.*', 'financial-reports.*', 'treasury.*', 'fiscal-periods.*'],
            'children' => [
                ['label' => 'ثبت سند حسابداری', 'route' => 'accounting-documents.create'],
                ['label' => 'مرکز گزارش‌های مالی', 'route' => 'financial-reports.index'],
                ['label' => 'اسناد حسابداری', 'route' => 'accounting-documents.index'],
                ['label' => 'دریافت/پرداخت', 'route' => 'treasury.index'],
                ['label' => 'دوره‌های مالی', 'route' => 'fiscal-periods.index'],
                ['label' => 'صورتحساب', 'route' => 'financial-reports.statement'],
                ['label' => 'دفتر کل', 'route' => 'financial-reports.general-ledger'],
                ['label' => 'تراز آزمایشی', 'route' => 'financial-reports.trial-balance'],
            ],
        ],
        [
            'label' => 'گزارش‌ها',
            'route' => 'management-reports.index',
            'active' => 'management-reports.*',
            'children' => [
                ['label' => 'مرکز گزارش‌ها', 'route' => 'management-reports.index'],
                ['label' => 'گزارش پرسنل', 'route' => 'management-reports.hr-employees'],
                ['label' => 'سوابق پرسنلی', 'route' => 'management-reports.hr-history'],
                ['label' => 'احکام کارگزینی', 'route' => 'management-reports.hr-employment-orders'],
                ['label' => 'تراز آزمایشی', 'route' => 'management-reports.trial-balance'],
                ['label' => 'موجودی انبار', 'route' => 'management-reports.warehouse-inventory'],
                ['label' => 'کاردکس انبار', 'route' => 'management-reports.warehouse-cardex'],
            ],
        ],
        [
            'label' => 'دسترسی‌ها',
            'route' => 'access.users.index',
            'active' => 'access.*',
            'children' => [
                ['label' => 'کاربران', 'route' => 'access.users.index'],
                ['label' => 'تعریف کاربر', 'route' => 'access.users.create'],
                ['label' => 'نقش‌ها', 'route' => 'access.roles.index'],
                ['label' => 'تعریف نقش', 'route' => 'access.roles.create'],
            ],
        ],
    ];
@endphp

<nav class="erp-top-nav">
    <div class="erp-nav-inner">
        <a href="{{ route('dashboard') }}" wire:navigate class="erp-brand">
            <span class="erp-brand-mark">ERP</span>
            <span>مدیریت ERP</span>
        </a>

        <div class="erp-nav-menu">
            @foreach ($navLinks as $link)
                @php
                    $patterns = (array) $link['active'];
                    $isActive = collect($patterns)->contains(fn ($pattern) => request()->routeIs($pattern));
                    $children = collect($link['children'] ?? [])->filter(function ($child) {
                        if (($child['type'] ?? null) === 'divider') {
                            return true;
                        }

                        return Route::has($child['route']);
                    });
                @endphp

                @if ($children->isNotEmpty())
                    <details class="erp-nav-item {{ $isActive ? 'is-active' : '' }}">
                        <summary class="erp-nav-link"><span>{{ $link['label'] }}</span></summary>
                        <div class="erp-nav-dropdown">
                            @foreach ($children as $child)
                                @if (($child['type'] ?? null) === 'divider')
                                    <span class="my-1 block border-t border-slate-200"></span>
                                @else
                                    <a href="{{ route($child['route'], $child['params'] ?? []) }}" wire:navigate class="erp-nav-dropdown-link">{{ $child['label'] }}</a>
                                @endif
                            @endforeach
                        </div>
                    </details>
                @else
                    <a href="{{ route($link['route']) }}" wire:navigate class="erp-nav-link {{ $isActive ? 'is-active' : '' }}">{{ $link['label'] }}</a>
                @endif
            @endforeach
        </div>

        <div class="erp-nav-user">
            @auth
                <a href="{{ route('profile.edit') }}" wire:navigate class="erp-user-link">{{ Auth::user()->name }}</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="erp-logout">خروج</button>
                </form>
            @endauth
        </div>
    </div>
</nav>

<script>
    (() => {
        const bindTopNav = () => {
            const nav = document.querySelector('.erp-top-nav');
            if (!nav || nav.dataset.dropdownBound === '1') return;
            nav.dataset.dropdownBound = '1';
            const dropdowns = Array.from(nav.querySelectorAll('.erp-nav-item'));
            dropdowns.forEach((item) => item.addEventListener('toggle', () => {
                if (item.open) dropdowns.forEach((other) => { if (other !== item) other.open = false; });
            }));
            document.addEventListener('click', (event) => {
                if (!nav.contains(event.target)) dropdowns.forEach((item) => { item.open = false; });
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') dropdowns.forEach((item) => { item.open = false; });
            });
        };
        document.addEventListener('DOMContentLoaded', bindTopNav);
        document.addEventListener('livewire:navigated', bindTopNav);
        bindTopNav();
    })();
</script>
