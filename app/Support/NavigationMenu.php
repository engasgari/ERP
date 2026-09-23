<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class NavigationMenu
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forUser(?User $user): array
    {
        return collect(self::definition())
            ->map(fn (array $item) => self::resolveItem($item, $user))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function definitionForBreadcrumbs(): array
    {
        return self::definition();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function definition(): array
    {
        return [
            [
                'label' => 'داشبورد',
                'route' => 'dashboard',
                'active' => ['dashboard'],
            ],
            [
                'label' => 'اطلاعات پایه',
                'route' => 'parties.index',
                'active' => ['parties.*', 'chart-accounts.*', 'company-settings.*', 'numbering-settings.*'],
                'children' => [
                    ['label' => 'اشخاص و شرکت‌ها', 'route' => 'parties.index', 'permission' => 'base-info.view'],
                    ['label' => 'تعریف شخص/شرکت', 'route' => 'parties.create', 'permission' => 'base-info.manage'],
                    ['label' => 'کدینگ حساب‌ها', 'route' => 'chart-accounts.index', 'permission' => 'accounting.view'],
                    ['label' => 'تنظیمات شرکت', 'route' => 'company-settings.edit', 'permission' => 'settings.manage'],
                    ['label' => 'شماره‌گذاری اسناد', 'route' => 'numbering-settings.index', 'permission' => 'settings.manage'],
                ],
            ],
            [
                'label' => 'بازرگانی',
                'route' => 'invoices.index',
                'active' => ['invoices.*', 'sales-reports.*', 'sales.intelligence', 'commerce.contractor-service-purchases.*'],
                'children' => [
                    ['label' => 'لیست فاکتورها', 'route' => 'invoices.index', 'permission' => 'commerce.view'],
                    ['type' => 'divider'],
                    ['label' => 'پیش‌فاکتور فروش', 'route' => 'invoices.create', 'params' => ['direction' => 'sale', 'document_type' => 'proforma'], 'permission' => 'commerce.manage'],
                    ['label' => 'فاکتور فروش', 'route' => 'invoices.create', 'params' => ['direction' => 'sale', 'document_type' => 'invoice'], 'permission' => 'commerce.manage'],
                    ['label' => 'فاکتور خرید', 'route' => 'invoices.create', 'params' => ['direction' => 'purchase', 'document_type' => 'invoice'], 'permission' => 'commerce.manage'],
                    ['label' => 'خرید خدمات پیمانکار', 'route' => 'commerce.contractor-service-purchases.index', 'permission' => 'commerce.view'],
                    ['type' => 'divider'],
                    ['label' => 'داشبورد فروش', 'route' => 'sales.intelligence', 'permission' => 'reports.sales.view'],
                ],
            ],
            [
                'label' => 'پروژه‌ها',
                'route' => 'projects.index',
                'active' => ['projects.*'],
                'children' => [
                    ['label' => 'لیست پروژه‌ها', 'route' => 'projects.index', 'permission' => 'projects.view'],
                    ['label' => 'تعریف پروژه', 'route' => 'projects.create', 'permission' => 'projects.manage'],
                ],
            ],
            [
                'label' => 'منابع انسانی',
                'route' => 'employees.index',
                'active' => ['employees.*', 'organization-units.*', 'jobs.*', 'positions.*', 'employment-orders.*', 'employment-contracts.*', 'employee-documents.*', 'work-shifts.*', 'work-calendars.*', 'work-groups.*'],
                'children' => [
                    ['label' => 'لیست پرسنل', 'route' => 'employees.index', 'permission' => 'employees.view'],
                    ['label' => 'پرسنل جدید', 'route' => 'employees.create', 'permission' => 'employees.manage'],
                    ['label' => 'مدارک پرسنلی', 'route' => 'employee-documents.index', 'permission' => 'hr.view'],
                    ['type' => 'divider'],
                    ['label' => 'ساختار سازمانی', 'route' => 'organization-units.index', 'permission' => 'hr.view'],
                    ['label' => 'مشاغل', 'route' => 'jobs.index', 'permission' => 'hr.view'],
                    ['label' => 'پست‌های سازمانی', 'route' => 'positions.index', 'permission' => 'hr.view'],
                    ['type' => 'divider'],
                    ['label' => 'احکام کارگزینی', 'route' => 'employment-orders.index', 'permission' => 'employment-orders.view'],
                    ['label' => 'قراردادها', 'route' => 'employment-contracts.index', 'permission' => 'contracts.view'],
                    ['type' => 'divider'],
                    ['label' => 'شیفت‌های کاری', 'route' => 'work-shifts.index', 'permission' => 'hr.view'],
                    ['label' => 'تقویم کاری', 'route' => 'work-calendars.index', 'permission' => 'hr.view'],
                    ['label' => 'گروه‌های کاری', 'route' => 'work-groups.index', 'permission' => 'hr.view'],
                ],
            ],
            [
                'label' => 'حقوق و دستمزد',
                'route' => 'payroll.periods.index',
                'active' => ['work-logs.*', 'attendance.*', 'payroll.*', 'payslips.*', 'insurance.*'],
                'children' => [
                    ['label' => 'لیست کارکرد', 'route' => 'work-logs.index', 'permission' => 'worklogs.view'],
                    ['label' => 'محاسبه کارکرد', 'route' => 'attendance.calculations', 'permission' => 'attendance.view'],
                    ['label' => 'خلاصه کارکرد ماهانه', 'route' => 'attendance.summaries', 'permission' => 'attendance.view'],
                    ['label' => 'درخواست‌های مرخصی', 'route' => 'attendance.leaves', 'permission' => 'attendance.view'],
                    ['label' => 'درخواست‌های ماموریت', 'route' => 'attendance.missions', 'permission' => 'attendance.view'],
                    ['label' => 'تخصیص گروه کاری', 'route' => 'work-groups.assignments', 'permission' => 'worklogs.manage'],
                    ['type' => 'divider'],
                    ['label' => 'دوره‌های حقوق', 'route' => 'payroll.periods.index', 'permission' => 'salaries.manage'],
                    ['label' => 'لیست و پرداخت حقوق', 'route' => 'payroll.payments.index', 'permission' => 'salaries.manage'],
                    ['label' => 'تنظیمات حسابداری حقوق', 'route' => 'payroll.accounting-settings.index', 'permission' => 'salaries.manage'],
                    ['type' => 'divider'],
                    ['label' => 'دوره‌های بیمه', 'route' => 'insurance.periods.index', 'permission' => 'insurance.view'],
                    ['label' => 'پرداخت بیمه', 'route' => 'insurance.payments.index', 'permission' => 'insurance.view'],
                ],
            ],
            [
                'label' => 'انبار',
                'route' => 'inventory-documents.index',
                'active' => ['items.*', 'warehouses.*', 'inventory-documents.*'],
                'children' => [
                    ['label' => 'اسناد انبار', 'route' => 'inventory-documents.index', 'permission' => 'inventory.view'],
                    ['label' => 'کالا و خدمات', 'route' => 'items.index', 'permission' => 'base-info.view'],
                    ['label' => 'تعریف کالا/خدمت', 'route' => 'items.create', 'permission' => 'base-info.manage'],
                    ['label' => 'لیست انبارها', 'route' => 'warehouses.index', 'permission' => 'warehouse.view'],
                    ['label' => 'تعریف انبار', 'route' => 'warehouses.create', 'permission' => 'warehouse.manage'],
                ],
            ],
            [
                'label' => 'مالی',
                'route' => 'accounting-documents.create',
                'active' => ['accounting-documents.*', 'financial-transactions.*', 'bank-accounts.*', 'treasury.*', 'partner-current-accounts.*', 'fiscal-periods.*'],
                'children' => [
                    ['label' => 'ثبت سند حسابداری', 'route' => 'accounting-documents.create', 'permission' => 'accounting.documents.create'],
                    ['label' => 'ثبت هزینه/درآمد', 'route' => 'financial-transactions.create', 'permission' => 'financial.manage'],
                    ['label' => 'دریافت و پرداخت', 'route' => 'treasury.index', 'permission' => 'treasury.manage'],
                    ['label' => 'اسناد حسابداری', 'route' => 'accounting-documents.index', 'permission' => 'accounting.view'],
                    ['label' => 'هزینه‌ها و درآمدها', 'route' => 'financial-transactions.index', 'permission' => 'financial.view'],
                    ['label' => 'حساب‌های جاری شرکا', 'route' => 'partner-current-accounts.index', 'permission' => 'treasury.manage'],
                    ['label' => 'حساب‌های بانکی', 'route' => 'bank-accounts.index', 'permission' => 'financial.manage'],
                    ['label' => 'دوره‌های مالی', 'route' => 'fiscal-periods.index', 'permission' => 'fiscal-years.manage'],
                ],
            ],
            [
                'label' => 'گزارش‌ها',
                'route' => 'management-reports.index',
                'active' => ['management-reports.*'],
                'children' => [
                    ['label' => 'مرکز گزارش‌ها', 'route' => 'management-reports.index', 'permission' => 'reports.view'],
                    ['type' => 'divider', 'label' => 'مالی'],
                    ['label' => 'تراز آزمایشی', 'route' => 'management-reports.trial-balance', 'permission' => 'reports.view'],
                    ['label' => 'صورتحساب اشخاص', 'route' => 'financial-reports.statement', 'permission' => 'financial.reports.view'],
                    ['label' => 'صورتحساب بانک', 'route' => 'financial-reports.show', 'params' => ['report' => 'bank-statement'], 'permission' => 'financial.reports.view'],
                    ['label' => 'صورتحساب صندوق', 'route' => 'financial-reports.show', 'params' => ['report' => 'cash-statement'], 'permission' => 'financial.reports.view'],
                    ['label' => 'گزارش‌های مالی پیشرفته', 'route' => 'financial-reports.index', 'permission' => 'financial.reports.view'],
                    ['type' => 'divider', 'label' => 'انبار'],
                    ['label' => 'موجودی انبار', 'route' => 'management-reports.warehouse-inventory', 'permission' => 'reports.view'],
                    ['label' => 'کاردکس انبار', 'route' => 'management-reports.warehouse-cardex', 'permission' => 'reports.view'],
                    ['type' => 'divider', 'label' => 'منابع انسانی'],
                    ['label' => 'گزارش پرسنل', 'route' => 'management-reports.hr-employees', 'permission' => 'reports.view'],
                    ['label' => 'سوابق پرسنلی', 'route' => 'management-reports.hr-history', 'permission' => 'reports.view'],
                    ['label' => 'احکام کارگزینی', 'route' => 'management-reports.hr-employment-orders', 'permission' => 'reports.view'],
                    ['type' => 'divider', 'label' => 'حقوق'],
                    ['label' => 'خلاصه حقوق', 'route' => 'management-reports.payroll-summary', 'permission' => 'reports.view'],
                    ['label' => 'ریز حقوق', 'route' => 'management-reports.payroll-register', 'permission' => 'reports.view'],
                    ['label' => 'آرشیو فیش حقوقی', 'route' => 'management-reports.payslip-archive', 'permission' => 'reports.view'],
                    ['label' => 'گزارش بیمه', 'route' => 'management-reports.insurance-summary', 'permission' => 'reports.view'],
                    ['label' => 'گزارش مالیات', 'route' => 'management-reports.tax-summary', 'permission' => 'reports.view'],
                ],
            ],
            [
                'label' => 'سیستم',
                'route' => 'access.users.index',
                'active' => ['access.*'],
                'permission' => 'users.manage',
                'children' => [
                    ['label' => 'کاربران', 'route' => 'access.users.index', 'permission' => 'users.manage'],
                    ['label' => 'تعریف کاربر', 'route' => 'access.users.create', 'permission' => 'users.manage'],
                    ['label' => 'نقش‌ها', 'route' => 'access.roles.index', 'permission' => 'users.manage'],
                    ['label' => 'تعریف نقش', 'route' => 'access.roles.create', 'permission' => 'users.manage'],
                ],
            ],
        ];
    }

  /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private static function resolveItem(array $item, ?User $user): ?array
    {
        $children = collect($item['children'] ?? [])
            ->filter(function (array $child) use ($user) {
                if (($child['type'] ?? null) === 'divider') {
                    return true;
                }

                $route = $child['route'] ?? null;

                if (! $route || ! Route::has($route)) {
                    return false;
                }

                return self::canSee($user, $child['permission'] ?? null);
            })
            ->values();

        $children = self::pruneDividers($children);

        if (! empty($item['children'])) {
            if ($children->where(fn (array $child) => ($child['type'] ?? null) !== 'divider')->isEmpty()) {
                return null;
            }

            $route = $item['route'] ?? null;

            if (! $route || ! Route::has($route) || ! self::canSee($user, $item['permission'] ?? null)) {
                return null;
            }

            $item['children'] = $children->all();

            return $item;
        }

        if (! Route::has($item['route'] ?? '') || ! self::canSee($user, $item['permission'] ?? null)) {
            return null;
        }

        return $item;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $children
     * @return Collection<int, array<string, mixed>>
     */
    private static function pruneDividers(Collection $children): Collection
    {
        $result = collect();
        $pendingDivider = null;

        foreach ($children as $child) {
            if (($child['type'] ?? null) === 'divider') {
                $pendingDivider = $child;

                continue;
            }

            if ($pendingDivider) {
                $result->push($pendingDivider);
                $pendingDivider = null;
            }

            $result->push($child);
        }

        return $result;
    }

    private static function canSee(?User $user, ?string $permission): bool
    {
        if (! $permission) {
            return true;
        }

        if (! $user) {
            return false;
        }

        return $user->hasPermission($permission);
    }
}
