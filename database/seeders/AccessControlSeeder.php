<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['key' => 'users.manage', 'title' => 'مدیریت کاربران و دسترسی‌ها', 'group' => 'مدیریت'],
            ['key' => 'base-info.view', 'title' => 'مشاهده اطلاعات پایه', 'group' => 'اطلاعات پایه'],
            ['key' => 'base-info.manage', 'title' => 'مدیریت اطلاعات پایه', 'group' => 'اطلاعات پایه'],
            ['key' => 'commerce.view', 'title' => 'مشاهده بازرگانی', 'group' => 'بازرگانی'],
            ['key' => 'commerce.manage', 'title' => 'مدیریت بازرگانی', 'group' => 'بازرگانی'],
            ['key' => 'accounting.view', 'title' => 'مشاهده حسابداری', 'group' => 'مالی'],
            ['key' => 'accounting.manage', 'title' => 'مدیریت حسابداری', 'group' => 'مالی'],
            ['key' => 'inventory.view', 'title' => 'مشاهده انبار پیشرفته', 'group' => 'انبار'],
            ['key' => 'inventory.manage', 'title' => 'مدیریت انبار پیشرفته', 'group' => 'انبار'],
            ['key' => 'settings.manage', 'title' => 'مدیریت تنظیمات شرکت و شماره‌گذاری', 'group' => 'تنظیمات'],
            ['key' => 'fiscal-years.manage', 'title' => 'مدیریت و بستن سال مالی', 'group' => 'مالی'],
            ['key' => 'projects.view', 'title' => 'مشاهده پروژه‌ها', 'group' => 'پروژه‌ها'],
            ['key' => 'projects.manage', 'title' => 'مدیریت پروژه‌ها', 'group' => 'پروژه‌ها'],
            ['key' => 'employees.view', 'title' => 'مشاهده پرسنل', 'group' => 'پرسنل'],
            ['key' => 'employees.manage', 'title' => 'مدیریت پرسنل', 'group' => 'پرسنل'],
            ['key' => 'worklogs.view', 'title' => 'مشاهده کارکرد', 'group' => 'کارکرد'],
            ['key' => 'worklogs.manage', 'title' => 'مدیریت کارکرد', 'group' => 'کارکرد'],
            ['key' => 'salaries.view', 'title' => 'مشاهده حقوق', 'group' => 'حقوق'],
            ['key' => 'salaries.manage', 'title' => 'مدیریت حقوق و پرداخت‌ها', 'group' => 'حقوق'],
            ['key' => 'warehouse.view', 'title' => 'مشاهده انبار', 'group' => 'انبار'],
            ['key' => 'warehouse.manage', 'title' => 'مدیریت انبار', 'group' => 'انبار'],
            ['key' => 'financial.view', 'title' => 'مشاهده مالی', 'group' => 'مالی'],
            ['key' => 'financial.manage', 'title' => 'مدیریت مالی', 'group' => 'مالی'],
            ['key' => 'reports.view', 'title' => 'مشاهده گزارش‌ها', 'group' => 'گزارش‌ها'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['key' => $permission['key']], $permission);
        }

        $allPermissions = Permission::pluck('id');
        $roles = [
            'admin' => ['title' => 'مدیر سیستم', 'description' => 'دسترسی کامل به همه بخش‌ها', 'permissions' => $allPermissions],
            'commercial_manager' => ['title' => 'مدیر بازرگانی', 'description' => 'مدیریت اشخاص، کالاها و فاکتورهای خرید و فروش', 'permissions' => Permission::whereIn('key', ['base-info.view', 'base-info.manage', 'commerce.view', 'commerce.manage', 'inventory.view'])->pluck('id')],
            'accounting_manager' => ['title' => 'مدیر مالی', 'description' => 'مدیریت حسابداری، کدینگ، اسناد و سال مالی', 'permissions' => Permission::whereIn('key', ['accounting.view', 'accounting.manage', 'financial.view', 'financial.manage', 'reports.view', 'fiscal-years.manage'])->pluck('id')],
            'hr_manager' => ['title' => 'مدیر منابع انسانی', 'description' => 'مدیریت پرسنل و کارکرد', 'permissions' => Permission::whereIn('key', ['employees.view', 'employees.manage', 'worklogs.view', 'worklogs.manage'])->pluck('id')],
            'payroll_viewer' => ['title' => 'مشاهده‌گر حقوق', 'description' => 'مشاهده حقوق کارمندهای مجاز', 'permissions' => Permission::whereIn('key', ['salaries.view'])->pluck('id')],
            'worklog_manager' => ['title' => 'مدیر کارکرد', 'description' => 'ثبت و مدیریت کارکرد کارمندهای مجاز', 'permissions' => Permission::whereIn('key', ['worklogs.view', 'worklogs.manage'])->pluck('id')],
            'warehouse_manager' => ['title' => 'مدیر انبار', 'description' => 'مدیریت انبار', 'permissions' => Permission::whereIn('key', ['warehouse.view', 'warehouse.manage', 'inventory.view', 'inventory.manage'])->pluck('id')],
            'accountant' => ['title' => 'حسابدار', 'description' => 'مدیریت مالی و مشاهده حقوق', 'permissions' => Permission::whereIn('key', ['accounting.view', 'accounting.manage', 'financial.view', 'financial.manage', 'salaries.view'])->pluck('id')],
            'report_viewer' => ['title' => 'مشاهده‌گر گزارش‌ها', 'description' => 'مشاهده گزارش‌های مدیریتی', 'permissions' => Permission::whereIn('key', ['reports.view', 'accounting.view'])->pluck('id')],
        ];

        foreach ($roles as $name => $data) {
            $role = Role::updateOrCreate(
                ['name' => $name],
                ['title' => $data['title'], 'description' => $data['description'], 'is_system' => $name === 'admin']
            );

            $role->permissions()->sync($data['permissions']);
        }

        $hrPermissions = [
            ['key' => 'hr.view', 'title' => 'مشاهده منابع انسانی', 'group' => 'منابع انسانی'],
            ['key' => 'hr.manage', 'title' => 'مدیریت منابع انسانی', 'group' => 'منابع انسانی'],
            ['key' => 'employment-orders.view', 'title' => 'مشاهده احکام کارگزینی', 'group' => 'منابع انسانی'],
            ['key' => 'employment-orders.manage', 'title' => 'مدیریت احکام کارگزینی', 'group' => 'منابع انسانی'],
            ['key' => 'employment-orders.approve', 'title' => 'تایید احکام کارگزینی', 'group' => 'منابع انسانی'],
            ['key' => 'contracts.view', 'title' => 'مشاهده قراردادها', 'group' => 'منابع انسانی'],
            ['key' => 'contracts.manage', 'title' => 'مدیریت قراردادها', 'group' => 'منابع انسانی'],
            ['key' => 'attendance.view', 'title' => 'مشاهده حضور و غیاب', 'group' => 'حضور و غیاب'],
            ['key' => 'attendance.manage', 'title' => 'مدیریت حضور و غیاب', 'group' => 'حضور و غیاب'],
            ['key' => 'attendance.import', 'title' => 'ورود اطلاعات تردد', 'group' => 'حضور و غیاب'],
            ['key' => 'payroll.view', 'title' => 'مشاهده حقوق و دستمزد', 'group' => 'حقوق و دستمزد'],
            ['key' => 'payroll.manage', 'title' => 'مدیریت حقوق و دستمزد', 'group' => 'حقوق و دستمزد'],
            ['key' => 'payroll.approve', 'title' => 'تایید حقوق', 'group' => 'حقوق و دستمزد'],
            ['key' => 'payroll.post', 'title' => 'صدور سند حقوق', 'group' => 'حقوق و دستمزد'],
            ['key' => 'salary-payments.view', 'title' => 'مشاهده پرداخت حقوق', 'group' => 'مالی'],
            ['key' => 'salary-payments.manage', 'title' => 'مدیریت پرداخت حقوق', 'group' => 'مالی'],
        ];

        foreach ($hrPermissions as $permission) {
            Permission::updateOrCreate(['key' => $permission['key']], $permission);
        }

        $hrRoles = [
            'hr_officer' => ['title' => 'کارشناس منابع انسانی', 'description' => 'ثبت و نگهداری پرونده پرسنلی', 'keys' => ['hr.view', 'hr.manage', 'employees.view', 'employees.manage', 'employment-orders.view', 'contracts.view']],
            'payroll_officer' => ['title' => 'کارشناس حقوق و دستمزد', 'description' => 'محاسبه و آماده‌سازی حقوق', 'keys' => ['payroll.view', 'payroll.manage', 'salaries.view', 'salaries.manage', 'attendance.view']],
            'payroll_manager' => ['title' => 'مدیر حقوق و دستمزد', 'description' => 'تایید حقوق و صدور سند', 'keys' => ['payroll.view', 'payroll.manage', 'payroll.approve', 'payroll.post', 'salaries.view', 'salaries.manage', 'employment-orders.view', 'employment-orders.approve']],
            'finance_manager' => ['title' => 'مدیر مالی', 'description' => 'مدیریت مالی و پرداخت حقوق', 'keys' => ['financial.view', 'financial.manage', 'salary-payments.view', 'salary-payments.manage', 'payroll.view', 'reports.view']],
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
