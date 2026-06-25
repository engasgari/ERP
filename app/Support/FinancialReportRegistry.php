<?php

namespace App\Support;

class FinancialReportRegistry
{
    public function catalog(): array
    {
        return [
            [
                'title' => 'صورت‌های مالی',
                'description' => 'تراز آزمایشی، دفاتر، ترازنامه، سود و زیان و صورت جریان وجوه نقد',
                'reports' => [
                    ['key' => 'trial-balance', 'title' => 'تراز آزمایشی', 'description' => 'مانده بدهکار/بستانکار و گردش دوره'],
                    ['key' => 'detailed-trial-balance', 'title' => 'تراز آزمایشی تفصیلی', 'description' => 'تراز در سطح حساب و طرف حساب'],
                    ['key' => 'general-ledger', 'title' => 'دفتر کل', 'description' => 'گردش حساب‌ها با مانده جاری'],
                    ['key' => 'detailed-ledger', 'title' => 'دفتر معین', 'description' => 'ریز اسناد و سطرهای حسابداری'],
                    ['key' => 'balance-sheet', 'title' => 'ترازنامه', 'description' => 'دارایی، بدهی و حقوق صاحبان سرمایه'],
                    ['key' => 'income-statement', 'title' => 'صورت سود و زیان', 'description' => 'درآمد، بهای تمام‌شده و هزینه‌ها'],
                    ['key' => 'cash-flow-statement', 'title' => 'صورت جریان وجوه نقد', 'description' => 'جریان نقد عملیاتی، سرمایه‌گذاری و تأمین مالی'],
                    ['key' => 'changes-in-equity', 'title' => 'صورت تغییرات حقوق صاحبان سهام', 'description' => 'مانده اول دوره، سود و زیان و تغییرات سرمایه'],
                ],
            ],
            [
                'title' => 'دریافتنی و پرداختنی',
                'description' => 'مانده مشتریان و تأمین‌کنندگان، صورتحساب و فهرست اسناد باز',
                'reports' => [
                    ['key' => 'accounts-receivable-aging', 'title' => 'سن مطالبات', 'description' => 'تحلیل مانده مشتریان بر اساس سررسید'],
                    ['key' => 'accounts-payable-aging', 'title' => 'سن بدهی‌ها', 'description' => 'تحلیل مانده تأمین‌کنندگان بر اساس سررسید'],
                    ['key' => 'customer-statement', 'title' => 'صورتحساب مشتری', 'description' => 'ریز گردش طرف حساب مشتری'],
                    ['key' => 'supplier-statement', 'title' => 'صورتحساب تأمین‌کننده', 'description' => 'ریز گردش طرف حساب تأمین‌کننده'],
                    ['key' => 'outstanding-invoices', 'title' => 'فاکتورهای باز', 'description' => 'فاکتورهای تأییدشده و تسویه‌نشده'],
                    ['key' => 'overdue-invoices', 'title' => 'فاکتورهای سررسید گذشته', 'description' => 'فاکتورهای باز با سررسید فرضی ۳۰ روزه'],
                ],
            ],
            [
                'title' => 'نقد و بانک',
                'description' => 'دفتر نقدی، بانک، مغایرت‌گیری و جریان نقد دوره‌ای',
                'reports' => [
                    ['key' => 'cash-book', 'title' => 'دفتر صندوق', 'description' => 'گردش صندوق‌های نقدی'],
                    ['key' => 'bank-statement', 'title' => 'صورتحساب بانک', 'description' => 'موجودی و گردش حساب‌های بانکی'],
                    ['key' => 'bank-reconciliation', 'title' => 'گزارش مغایرت بانکی', 'description' => 'تطبیق دفتر بانک با تراکنش‌های ثبت‌شده'],
                    ['key' => 'cash-flow-by-period', 'title' => 'جریان نقد بر اساس دوره', 'description' => 'جریان نقد به‌تفکیک ماه/دوره'],
                ],
            ],
            [
                'title' => 'مالیات',
                'description' => 'مالیات فروش، خرید، VAT و سطرهای مالیاتی',
                'reports' => [
                    ['key' => 'sales-tax', 'title' => 'گزارش مالیات فروش', 'description' => 'مالیات فروش فاکتورهای فروش'],
                    ['key' => 'purchase-tax', 'title' => 'گزارش مالیات خرید', 'description' => 'مالیات خرید فاکتورهای خرید'],
                    ['key' => 'vat-summary', 'title' => 'خلاصه VAT', 'description' => 'جمع فروش، خرید، مالیات و مانده قابل پرداخت'],
                    ['key' => 'tax-transactions', 'title' => 'گردش مالیاتی', 'description' => 'سطرهای سند مرتبط با حساب‌های مالیاتی'],
                ],
            ],
            [
                'title' => 'تحلیل مدیریت',
                'description' => 'تحلیل هزینه، درآمد، پروژه، مشتری، مرکز هزینه و واحد سازمانی',
                'reports' => [
                    ['key' => 'expense-analysis-by-account', 'title' => 'تحلیل هزینه به تفکیک حساب', 'description' => 'هزینه‌ها بر اساس حساب‌های هزینه'],
                    ['key' => 'revenue-analysis-by-account', 'title' => 'تحلیل درآمد به تفکیک حساب', 'description' => 'درآمدها بر اساس حساب‌های درآمد'],
                    ['key' => 'profitability-by-project', 'title' => 'سودآوری پروژه', 'description' => 'سود ناخالص هر پروژه'],
                    ['key' => 'profitability-by-customer', 'title' => 'سودآوری مشتری', 'description' => 'سود ناخالص بر اساس طرف حساب مشتری'],
                    ['key' => 'cost-center-report', 'title' => 'گزارش مرکز هزینه', 'description' => 'گردش و مانده مراکز هزینه'],
                    ['key' => 'department-financial-performance', 'title' => 'عملکرد مالی واحدها', 'description' => 'تحلیل مالی واحدهای سازمانی'],
                ],
            ],
            [
                'title' => 'سند و حسابرسی',
                'description' => 'ثبت سند، گزارش‌های بازه‌ای و لاگ حسابرسی',
                'reports' => [
                    ['key' => 'journal-entries', 'title' => 'گزارش اسناد حسابداری', 'description' => 'فهرست اسناد و جمع سطرها'],
                    ['key' => 'journal-entries-by-date', 'title' => 'اسناد به تفکیک تاریخ', 'description' => 'گروه‌بندی اسناد بر اساس تاریخ'],
                    ['key' => 'journal-entries-by-account', 'title' => 'اسناد به تفکیک حساب', 'description' => 'گردش سندی هر حساب'],
                    ['key' => 'audit-trail', 'title' => 'ردیاب حسابرسی', 'description' => 'لاگ رخدادهای حسابرسی'],
                    ['key' => 'deleted-modified-transactions', 'title' => 'اسناد حذف/ویرایش‌شده', 'description' => 'اسناد و تراکنش‌های تغییر یافته یا حذف شده'],
                ],
            ],
        ];
    }

    public function reportDefinitions(string $key): ?array
    {
        foreach ($this->catalog() as $category) {
            foreach ($category['reports'] as $report) {
                if ($report['key'] === $key) {
                    return $report;
                }
            }
        }

        return null;
    }
}

