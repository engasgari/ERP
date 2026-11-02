<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\AttendancePermissions;
use App\Support\CommercePermissions;
use App\Support\EmployeePermissions;
use App\Support\HrPermissions;
use App\Support\InventoryPermissions;
use App\Support\PayrollPermissions;
use App\Support\ProjectPermissions;
use App\Support\TreasuryPermissions;
use App\Support\WorklogPermissions;
use Illuminate\Database\Seeder;

class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['key' => 'users.manage', 'title' => 'مدیریت کاربران و دسترسی‌ها', 'group' => 'مدیریت'],
            ['key' => 'base-info.view', 'title' => 'مشاهده اطلاعات پایه', 'group' => 'اطلاعات پایه'],
            ['key' => 'base-info.manage', 'title' => 'مدیریت اطلاعات پایه', 'group' => 'اطلاعات پایه'],
            ...CommercePermissions::definitions(),
            ...InventoryPermissions::definitions(),
            ...ProjectPermissions::definitions(),
            ...EmployeePermissions::definitions(),
            ...AttendancePermissions::definitions(),
            ...PayrollPermissions::definitions(),
            ...PayrollPermissions::legacyDefinitions(),
            ...WorklogPermissions::definitions(),
            ...HrPermissions::definitions(),
            ...TreasuryPermissions::definitions(),
            ['key' => 'accounting.view', 'title' => 'مشاهده حسابداری', 'group' => 'مالی'],
            ['key' => 'accounting.manage', 'title' => 'مدیریت حسابداری', 'group' => 'مالی'],
            ['key' => 'settings.manage', 'title' => 'مدیریت تنظیمات شرکت و شماره‌گذاری', 'group' => 'تنظیمات'],
            ['key' => 'fiscal-years.manage', 'title' => 'مدیریت و بستن سال مالی', 'group' => 'مالی'],
            ['key' => 'fiscal.view', 'title' => 'مشاهده سال مالی', 'group' => 'مالی'],
            ['key' => 'fiscal.close', 'title' => 'بستن سال مالی', 'group' => 'مالی'],
            ['key' => 'fiscal.open', 'title' => 'بازکردن سال مالی', 'group' => 'مالی'],
            ['key' => 'fiscal.reopen', 'title' => 'بازگشایی سال مالی', 'group' => 'مالی'],
            ['key' => 'fiscal.manage_sequences', 'title' => 'مدیریت شماره‌گذاری سال مالی', 'group' => 'مالی'],
            ['key' => 'financial.view', 'title' => 'مشاهده مالی', 'group' => 'مالی'],
            ['key' => 'financial.manage', 'title' => 'مدیریت مالی', 'group' => 'مالی'],
            ['key' => 'salary-payments.view', 'title' => 'مشاهده پرداخت حقوق', 'group' => 'مالی'],
            ['key' => 'salary-payments.manage', 'title' => 'مدیریت پرداخت حقوق', 'group' => 'مالی'],
            ['key' => 'reports.view', 'title' => 'مشاهده گزارش‌ها', 'group' => 'گزارش‌ها'],
            ['key' => 'reports.sales.view', 'title' => 'مشاهده گزارش‌های فروش', 'group' => 'گزارش‌ها'],
            ['key' => 'reports.sales.export', 'title' => 'خروجی گزارش‌های فروش', 'group' => 'گزارش‌ها'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['key' => $permission['key']], $permission);
        }

        $allPermissions = Permission::pluck('id');
        $roles = [
            'admin' => ['title' => 'مدیر سیستم', 'description' => 'دسترسی کامل به همه بخش‌ها', 'permissions' => $allPermissions],
            'commercial_manager' => ['title' => 'مدیر بازرگانی', 'description' => 'مدیریت اشخاص، کالاها و فاکتورهای خرید و فروش', 'permissions' => Permission::whereIn('key', array_merge([
                'base-info.view',
                'base-info.manage',
                'inventory.view',
                'reports.sales.view',
                'reports.sales.export',
            ], CommercePermissions::allKeys()))->pluck('id')],
            'accounting_manager' => ['title' => 'مدیر مالی', 'description' => 'مدیریت حسابداری، کدینگ، اسناد و سال مالی', 'permissions' => Permission::whereIn('key', ['accounting.view', 'accounting.manage', 'financial.view', 'financial.manage', 'reports.view', 'fiscal-years.manage', 'fiscal.view', 'fiscal.close', 'fiscal.open', 'fiscal.reopen', 'fiscal.manage_sequences'])->pluck('id')],
            'hr_manager' => ['title' => 'مدیر منابع انسانی', 'description' => 'مدیریت پرسنل و کارکرد', 'permissions' => Permission::whereIn('key', array_merge(
                EmployeePermissions::allKeys(),
                AttendancePermissions::allKeys(),
                WorklogPermissions::allKeys(),
                HrPermissions::allKeys()
            ))->pluck('id')],
            'payroll_viewer' => ['title' => 'مشاهده‌گر حقوق', 'description' => 'مشاهده حقوق کارمندهای مجاز', 'permissions' => Permission::whereIn('key', [
                PayrollPermissions::VIEW,
            ])->pluck('id')],
            'worklog_manager' => ['title' => 'مدیر کارکرد', 'description' => 'ثبت و مدیریت کارکرد کارمندهای مجاز', 'permissions' => Permission::whereIn('key', WorklogPermissions::allKeys())->pluck('id')],
            'project_manager' => ['title' => 'مدیر پروژه', 'description' => 'مدیریت پروژه‌ها', 'permissions' => Permission::whereIn('key', ProjectPermissions::allKeys())->pluck('id')],
            'warehouse_manager' => ['title' => 'مدیر انبار', 'description' => 'مدیریت انبار', 'permissions' => Permission::whereIn('key', InventoryPermissions::allKeys())->pluck('id')],
            'treasury_manager' => ['title' => 'مدیر خزانه', 'description' => 'مدیریت دریافت و پرداخت', 'permissions' => Permission::whereIn('key', TreasuryPermissions::allKeys())->pluck('id')],
            'accountant' => ['title' => 'حسابدار', 'description' => 'مدیریت مالی و مشاهده حقوق', 'permissions' => Permission::whereIn('key', ['accounting.view', 'accounting.manage', 'financial.view', 'financial.manage', PayrollPermissions::VIEW])->pluck('id')],
            'report_viewer' => ['title' => 'مشاهده‌گر گزارش‌ها', 'description' => 'مشاهده گزارش‌های مدیریتی', 'permissions' => Permission::whereIn('key', ['reports.view', 'reports.sales.view', 'accounting.view'])->pluck('id')],
        ];

        foreach ($roles as $name => $data) {
            $role = Role::updateOrCreate(
                ['name' => $name],
                ['title' => $data['title'], 'description' => $data['description'], 'is_system' => $name === 'admin']
            );

            $role->permissions()->sync($data['permissions']);
        }

        $hrRoles = [
            'hr_officer' => ['title' => 'کارشناس منابع انسانی', 'description' => 'ثبت و نگهداری پرونده پرسنلی', 'keys' => array_merge(
                HrPermissions::allKeys(),
                EmployeePermissions::allKeys()
            )],
            'payroll_officer' => ['title' => 'کارشناس حقوق و دستمزد', 'description' => 'محاسبه و آماده‌سازی حقوق', 'keys' => array_merge(
                PayrollPermissions::allKeys(),
                [AttendancePermissions::VIEW, AttendancePermissions::CALCULATE]
            )],
            'payroll_manager' => ['title' => 'مدیر حقوق و دستمزد', 'description' => 'تایید حقوق و صدور سند', 'keys' => array_merge(
                PayrollPermissions::allKeys(),
                [HrPermissions::ORDERS_VIEW, HrPermissions::ORDERS_APPROVE]
            )],
            'finance_manager' => ['title' => 'مدیر مالی', 'description' => 'مدیریت مالی و پرداخت حقوق', 'keys' => array_merge([
                'financial.view',
                'financial.manage',
                'salary-payments.view',
                'salary-payments.manage',
                PayrollPermissions::VIEW,
                'reports.view',
                PayrollPermissions::INSURANCE_VIEW,
                PayrollPermissions::INSURANCE_PAYMENT,
            ], TreasuryPermissions::allKeys())],
        ];

        foreach ($hrRoles as $name => $data) {
            $role = Role::updateOrCreate(
                ['name' => $name],
                ['title' => $data['title'], 'description' => $data['description'], 'is_system' => false]
            );
            $role->permissions()->syncWithoutDetaching(Permission::whereIn('key', $data['keys'])->pluck('id'));
        }

        Role::where('name', 'admin')->first()?->permissions()->sync(Permission::pluck('id'));

        $adminUser = User::where('email', 'mahdi@aale.ir')->first() ?? User::orderBy('id')->first();
        if ($adminUser) {
            $adminUser->roles()->syncWithoutDetaching([Role::where('name', 'admin')->value('id')]);
        }
    }
}
