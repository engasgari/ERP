<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Route patterns treated as report pages (context-aware back navigation)
    |--------------------------------------------------------------------------
    */
    'report_patterns' => [
        'sales-reports.*',
        'financial-reports.*',
        'management-reports.*',
        '*.project-report',
        '*.employee-report',
        'bank-accounts.statement',
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes whose full URL (query string included) should be remembered
    |--------------------------------------------------------------------------
    */
    'remember_patterns' => [
        '*.index',
        'dashboard',
        'company-settings.edit',
        'attendance.*',
        'work-groups.assignments',
        'bank-accounts.statement',
        'financial-reports.statement',
        'financial-reports.general-ledger',
        'financial-reports.trial-balance',
        'financial-reports.balance-sheet',
        'financial-reports.profit-loss',
        'financial-reports.aging',
        'financial-reports.account-statement',
        'financial-reports.employee-statement',
        'projects.costing',
    ],

    /*
    |--------------------------------------------------------------------------
    | Previous URLs from these routes must not become report return targets
    |--------------------------------------------------------------------------
    */
    'excluded_previous_patterns' => [
        'login',
        'logout',
        'password.*',
        'register',
        'verification.*',
        'sales-reports.*',
        'financial-reports.*',
        'management-reports.*',
    ],

    /*
    |--------------------------------------------------------------------------
    | CRUD action labels (suffix after last dot in route name)
    |--------------------------------------------------------------------------
    */
    'action_labels' => [
        'index' => null,
        'create' => 'ثبت جدید',
        'edit' => 'ویرایش',
        'show' => 'مشاهده',
        'import' => 'ورود از اکسل',
        'import.form' => 'ورود از اکسل',
        'statement' => 'صورتحساب',
        'costing' => 'گزارش هزینه',
        'assignments' => 'تخصیص گروه کاری',
        'calculations' => 'محاسبه کارکرد',
        'summaries' => 'خلاصه کارکرد ماهانه',
        'leaves' => 'درخواست‌های مرخصی',
        'missions' => 'درخواست‌های ماموریت',
        'employee-access' => 'دسترسی پرسنلی',
        'project-report' => 'گزارش پروژه',
        'employee-report' => 'گزارش پرسنل',
    ],

    /*
    |--------------------------------------------------------------------------
    | Explicit route labels (override auto / navigation menu labels)
    |--------------------------------------------------------------------------
    */
    'route_labels' => [
        'dashboard' => 'داشبورد',
        'invoices.index' => 'فاکتورها',
        'invoices.create' => 'ثبت سند بازرگانی',
        'invoices.show' => 'مشاهده فاکتور',
        'invoices.edit' => 'ویرایش فاکتور',
        'parties.index' => 'اشخاص و شرکت‌ها',
        'parties.create' => 'تعریف شخص/شرکت',
        'items.index' => 'کالا و خدمات',
        'items.create' => 'تعریف کالا/خدمت',
        'chart-accounts.index' => 'کدینگ حساب‌ها',
        'chart-accounts.create' => 'تعریف حساب',
        'warehouses.index' => 'انبارها',
        'warehouses.create' => 'تعریف انبار',
        'warehouses.show' => 'جزئیات انبار',
        'warehouses.edit' => 'ویرایش انبار',
        'projects.index' => 'پروژه‌ها',
        'projects.create' => 'تعریف پروژه',
        'projects.show' => 'جزئیات پروژه',
        'projects.edit' => 'ویرایش پروژه',
        'projects.costing' => 'گزارش هزینه پروژه',
        'employees.index' => 'پرسنل',
        'employees.create' => 'پرسنل جدید',
        'employees.show' => 'پرونده پرسنلی',
        'employees.edit' => 'ویرایش پرسنل',
        'accounting-documents.index' => 'اسناد حسابداری',
        'accounting-documents.create' => 'ثبت سند حسابداری',
        'accounting-documents.show' => 'جزئیات سند',
        'accounting-documents.edit' => 'ویرایش سند',
        'financial-transactions.index' => 'هزینه‌ها و درآمدها',
        'financial-transactions.create' => 'ثبت هزینه/درآمد',
        'financial-transactions.show' => 'جزئیات تراکنش',
        'financial-transactions.edit' => 'ویرایش تراکنش',
        'treasury.index' => 'دریافت و پرداخت',
        'treasury.create' => 'ثبت تراکنش',
        'treasury.edit' => 'ویرایش تراکنش',
        'partner-current-accounts.index' => 'حساب‌های جاری شرکا',
        'partner-current-accounts.create' => 'ثبت انتقال',
        'bank-accounts.index' => 'حساب‌های بانکی',
        'bank-accounts.statement' => 'صورتحساب بانک',
        'fiscal-periods.index' => 'دوره‌های مالی',
        'inventory-documents.index' => 'اسناد انبار',
        'company-settings.edit' => 'تنظیمات شرکت',
        'numbering-settings.index' => 'شماره‌گذاری اسناد',
        'sales-reports.index' => 'داشبورد فروش',
        'sales.intelligence' => 'داشبورد فروش',
        'financial-reports.index' => 'گزارش‌های مالی',
        'management-reports.index' => 'مرکز گزارش‌ها',
        'access.users.index' => 'کاربران',
        'access.users.create' => 'تعریف کاربر',
        'access.roles.index' => 'نقش‌ها',
        'access.roles.create' => 'تعریف نقش',
        'self-service.index' => 'خدمات خودکار',
        'profile.edit' => 'پروفایل',
        'payroll.periods.index' => 'دوره‌های حقوق',
        'payroll.payments.index' => 'لیست و پرداخت حقوق',
        'payroll.accounting-settings.index' => 'تنظیمات حسابداری حقوق',
        'insurance.periods.index' => 'دوره‌های بیمه',
        'insurance.payments.index' => 'پرداخت بیمه',
        'insurance.payments.show' => 'جزئیات پرداخت بیمه',
    ],

    /*
    |--------------------------------------------------------------------------
    | Report route defaults when no entry context is available
    |--------------------------------------------------------------------------
    */
    'report_defaults' => [
        'sales-reports' => [
            'hub' => ['route' => 'sales.intelligence', 'label' => 'داشبورد فروش'],
            'module' => ['route' => 'invoices.index', 'label' => 'بازرگانی'],
            'fallback_parent' => ['route' => 'invoices.index', 'label' => 'فاکتورها'],
        ],
        'financial-reports' => [
            'hub' => ['route' => 'financial-reports.index', 'label' => 'گزارش‌های مالی'],
            'module' => ['route' => 'management-reports.index', 'label' => 'گزارش‌ها'],
            'fallback_parent' => ['route' => 'financial-reports.index', 'label' => 'گزارش‌های مالی'],
        ],
        'management-reports' => [
            'hub' => ['route' => 'management-reports.index', 'label' => 'مرکز گزارش‌ها'],
            'module' => ['route' => 'management-reports.index', 'label' => 'گزارش‌ها'],
            'fallback_parent' => ['route' => 'management-reports.index', 'label' => 'مرکز گزارش‌ها'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Report page titles keyed by route name (or sales/financial report key)
    |--------------------------------------------------------------------------
    */
    'report_titles' => [
        'sales-reports.show' => [
            'dashboard' => 'داشبورد فروش',
            'sales' => 'گزارش اصلی فروش',
            'by-customer' => 'فروش به تفکیک مشتری',
            'by-product' => 'فروش به تفکیک محصول',
            'receivables' => 'مطالبات فروش',
            'salesperson' => 'عملکرد فروشندگان',
            'profitability' => 'سود و حاشیه سود',
            'returns-discounts' => 'برگشت و تخفیفات',
        ],
        'management-reports.trial-balance' => 'تراز آزمایشی',
        'management-reports.warehouse-inventory' => 'موجودی انبار',
        'management-reports.warehouse-cardex' => 'کاردکس انبار',
        'management-reports.hr-employees' => 'گزارش پرسنل',
        'management-reports.hr-history' => 'سوابق پرسنلی',
        'management-reports.hr-employment-orders' => 'احکام کارگزینی',
        'management-reports.attendance-monthly' => 'کارکرد ماهانه',
        'management-reports.attendance-daily' => 'کارکرد روزانه',
        'management-reports.attendance-exceptions' => 'استثنائات کارکرد',
        'management-reports.payroll-summary' => 'خلاصه حقوق',
        'management-reports.payroll-register' => 'ریز حقوق',
        'management-reports.payslip-archive' => 'آرشیو فیش حقوقی',
        'management-reports.insurance-summary' => 'گزارش بدهی بیمه',
        'management-reports.insurance-employees' => 'گزارش بیمه پرسنلی',
        'management-reports.tax-summary' => 'گزارش مالیات',
        'financial-reports.general-ledger' => 'دفتر کل',
        'financial-reports.trial-balance' => 'تراز آزمایشی',
        'financial-reports.statement' => 'صورتحساب اشخاص',
        'financial-reports.employee-statement' => 'صورتحساب پرسنل',
        'financial-reports.account-statement' => 'صورتحساب حساب',
        'financial-reports.balance-sheet' => 'ترازنامه',
        'financial-reports.profit-loss' => 'سود و زیان',
        'financial-reports.aging' => 'سن مطالبات',
        'financial-transactions.project-report' => 'گزارش مالی پروژه',
        'work-logs.project-report' => 'گزارش کارکرد پروژه',
        'work-logs.employee-report' => 'گزارش کارکرد پرسنل',
        'bank-accounts.statement' => 'صورتحساب بانک',
    ],
];
