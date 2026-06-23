<?php

namespace App\Services;

class ReportCenterService
{
    public function sections(): array
    {
        return [
            [
                'key' => 'commercial',
                'title' => 'بازرگانی',
                'description' => 'گزارش‌های فروش، خرید، مانده اشخاص و پیگیری وصول و پرداخت.',
                'reports' => [
                    [
                        'title' => 'خلاصه تراکنش‌های مالی',
                        'description' => 'جمع‌بندی درآمدها و هزینه‌های ثبت‌شده در سیستم',
                        'route' => route('financial-transactions.summary'),
                    ],
                    [
                        'title' => 'صورتحساب اشخاص',
                        'description' => 'ریز گردش حساب مشتریان و تأمین‌کنندگان',
                        'route' => route('financial-reports.statement'),
                    ],
                    [
                        'title' => 'سن مطالبات',
                        'description' => 'مانده بدهکار مشتریان به تفکیک سررسید',
                        'route' => route('financial-reports.show', ['report' => 'accounts-receivable-aging']),
                    ],
                    [
                        'title' => 'سن بدهی‌ها',
                        'description' => 'مانده بستانکار تأمین‌کنندگان به تفکیک سررسید',
                        'route' => route('financial-reports.show', ['report' => 'accounts-payable-aging']),
                    ],
                ],
            ],
            [
                'key' => 'warehouse',
                'title' => 'انبار',
                'description' => 'گزارش‌های موجودی، کاردکس و گردش کالا و خدمات.',
                'reports' => [
                    [
                        'title' => 'کاردکس انبار',
                        'description' => 'ورود و خروج کالا با مانده لحظه‌ای',
                        'route' => route('management-reports.warehouse-cardex'),
                    ],
                    [
                        'title' => 'موجودی انبار',
                        'description' => 'مانده کالاها بر اساس انبار، پروژه و دسته‌بندی',
                        'route' => route('management-reports.warehouse-inventory'),
                    ],
                ],
            ],
            [
                'key' => 'payroll',
                'title' => 'حقوق و دستمزد',
                'description' => 'خلاصه حقوق، کسورات، بیمه، مالیات و آرشیو پرداخت‌ها.',
                'reports' => [
                    [
                        'title' => 'خلاصه حقوق',
                        'description' => 'جمع حقوق ناخالص، کسورات و خالص پرداختی',
                        'route' => route('management-reports.payroll-summary'),
                    ],
                    [
                        'title' => 'ریز حقوق',
                        'description' => 'لیست محاسبات حقوق و آیتم‌های پرداختی',
                        'route' => route('management-reports.payroll-register'),
                    ],
                    [
                        'title' => 'آرشیو فیش حقوقی',
                        'description' => 'فیش‌های صادرشده و وضعیت نهایی آن‌ها',
                        'route' => route('management-reports.payslip-archive'),
                    ],
                    [
                        'title' => 'خلاصه بیمه',
                        'description' => 'سهم بیمه کارمند، کارفرما و بیکاری',
                        'route' => route('management-reports.insurance-summary'),
                    ],
                    [
                        'title' => 'خلاصه مالیات',
                        'description' => 'درآمد مشمول و مالیات محاسبه‌شده',
                        'route' => route('management-reports.tax-summary'),
                    ],
                    [
                        'title' => 'گزارش مالی پرسنل',
                        'description' => 'جمع‌بندی مالی کارکنان در یک نمای خلاصه',
                        'route' => route('salaries.financial-report'),
                    ],
                ],
            ],
            [
                'key' => 'attendance',
                'title' => 'کارکرد',
                'description' => 'گزارش‌های کارکرد روزانه، ماهانه و استثنائات حضور و غیاب.',
                'reports' => [
                    [
                        'title' => 'کارکرد ماهانه',
                        'description' => 'خلاصه کارکرد، اضافه‌کاری و غیبت هر دوره',
                        'route' => route('management-reports.attendance-monthly'),
                    ],
                    [
                        'title' => 'اضافه‌کاری و تاخیر',
                        'description' => 'تحلیل اضافه‌کاری، تاخیر، تعجیل و غیبت',
                        'route' => route('management-reports.attendance-exceptions'),
                    ],
                    [
                        'title' => 'محاسبات کارکرد',
                        'description' => 'ورود مستقیم به بخش محاسبات حضور و غیاب',
                        'route' => route('attendance.calculations'),
                    ],
                    [
                        'title' => 'خلاصه کارکرد',
                        'description' => 'نمای فشرده وضعیت حضور، مرخصی و ماموریت',
                        'route' => route('attendance.summaries'),
                    ],
                    [
                        'title' => 'مرخصی‌ها',
                        'description' => 'گزارش درخواست‌ها و ثبت‌های مرخصی',
                        'route' => route('attendance.leaves'),
                    ],
                    [
                        'title' => 'ماموریت‌ها',
                        'description' => 'گزارش درخواست‌ها و ثبت‌های ماموریت',
                        'route' => route('attendance.missions'),
                    ],
                ],
            ],
            [
                'key' => 'personnel',
                'title' => 'پرسنل',
                'description' => 'گزارش‌های پرسنلی، سوابق و احکام کارگزینی.',
                'reports' => [
                    [
                        'title' => 'لیست پرسنل',
                        'description' => 'فهرست کارکنان فعال و غیرفعال',
                        'route' => route('management-reports.hr-employees'),
                    ],
                    [
                        'title' => 'سوابق پرسنلی',
                        'description' => 'تاریخچه احکام و تغییرات پرسنل',
                        'route' => route('management-reports.hr-history'),
                    ],
                    [
                        'title' => 'احکام کارگزینی',
                        'description' => 'فهرست احکام و وضعیت تایید',
                        'route' => route('management-reports.hr-employment-orders'),
                    ],
                ],
            ],
            [
                'key' => 'financial',
                'title' => 'مالی',
                'description' => 'گزارش‌های استاندارد مالی، دفتر کل، تراز و تحلیل سود و زیان.',
                'reports' => [
                    [
                        'title' => 'تراز آزمایشی',
                        'description' => 'مانده بدهکار، بستانکار و گردش دوره',
                        'route' => route('management-reports.trial-balance'),
                    ],
                    [
                        'title' => 'دفتر کل',
                        'description' => 'گردش حساب‌ها در سطح کل',
                        'route' => route('financial-reports.general-ledger'),
                    ],
                    [
                        'title' => 'دفتر معین',
                        'description' => 'ریز اسناد و سطرهای هر حساب',
                        'route' => route('financial-reports.show', ['report' => 'detailed-ledger']),
                    ],
                    [
                        'title' => 'ترازنامه',
                        'description' => 'دارایی، بدهی و حقوق صاحبان سهام',
                        'route' => route('financial-reports.balance-sheet'),
                    ],
                    [
                        'title' => 'سود و زیان',
                        'description' => 'درآمد، بهای تمام‌شده و هزینه‌ها',
                        'route' => route('financial-reports.profit-loss'),
                    ],
                    [
                        'title' => 'جریان وجوه نقد',
                        'description' => 'ورود و خروج نقد و بانک در دوره',
                        'route' => route('financial-reports.show', ['report' => 'cash-flow-statement']),
                    ],
                    [
                        'title' => 'گردش بانک',
                        'description' => 'صورت‌حساب و گردش حساب‌های بانکی',
                        'route' => route('financial-reports.show', ['report' => 'bank-statement']),
                    ],
                    [
                        'title' => 'مغایرت بانکی',
                        'description' => 'تطبیق گردش بانک با اسناد ثبت‌شده',
                        'route' => route('financial-reports.show', ['report' => 'bank-reconciliation']),
                    ],
                    [
                        'title' => 'صورتحساب مشتری و تامین‌کننده',
                        'description' => 'جزئیات بدهکار و بستانکار طرف حساب‌ها',
                        'route' => route('financial-reports.statement'),
                    ],
                    [
                        'title' => 'گزارش اسناد حسابداری',
                        'description' => 'فهرست اسناد، دفاتر و لاگ‌های حسابرسی',
                        'route' => route('financial-reports.show', ['report' => 'journal-entries']),
                    ],
                    [
                        'title' => 'مرکز هزینه',
                        'description' => 'گردش و مانده مراکز هزینه',
                        'route' => route('financial-reports.show', ['report' => 'cost-center-report']),
                    ],
                    [
                        'title' => 'سودآوری پروژه',
                        'description' => 'تحلیل سود و زیان بر اساس پروژه',
                        'route' => route('financial-reports.show', ['report' => 'profitability-by-project']),
                    ],
                    [
                        'title' => 'سودآوری مشتری',
                        'description' => 'تحلیل سود و زیان بر اساس مشتری',
                        'route' => route('financial-reports.show', ['report' => 'profitability-by-customer']),
                    ],
                ],
            ],
        ];
    }

    public function findSection(?string $key): ?array
    {
        $sections = $this->sections();
        $key = $key ?: ($sections[0]['key'] ?? null);

        foreach ($sections as $section) {
            if ($section['key'] === $key) {
                return $section;
            }
        }

        return $sections[0] ?? null;
    }
}
