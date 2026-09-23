<?php

namespace Database\Seeders;

use App\Models\Crm\LeadSource;
use App\Models\Crm\Pipeline;
use App\Models\Crm\PipelineStage;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class CrmSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedLeadSources();
        $this->seedPipeline();
        $this->seedPermissions();
    }

    private function seedLeadSources(): void
    {
        $sources = [
            ['code' => 'website', 'title' => 'وب‌سایت', 'sort_order' => 1],
            ['code' => 'instagram', 'title' => 'اینستاگرام', 'sort_order' => 2],
            ['code' => 'google', 'title' => 'گوگل', 'sort_order' => 3],
            ['code' => 'referral', 'title' => 'معرفی', 'sort_order' => 4],
            ['code' => 'existing_customer', 'title' => 'مشتری موجود', 'sort_order' => 5],
            ['code' => 'phone', 'title' => 'تماس تلفنی', 'sort_order' => 6],
            ['code' => 'whatsapp', 'title' => 'واتساپ', 'sort_order' => 7],
            ['code' => 'advertisement', 'title' => 'تبلیغات', 'sort_order' => 8],
            ['code' => 'exhibition', 'title' => 'نمایشگاه', 'sort_order' => 9],
            ['code' => 'other', 'title' => 'سایر', 'sort_order' => 10],
        ];

        foreach ($sources as $source) {
            LeadSource::updateOrCreate(['code' => $source['code']], $source + ['is_active' => true]);
        }
    }

    private function seedPipeline(): void
    {
        $pipeline = Pipeline::updateOrCreate(
            ['code' => 'default_sales'],
            ['name' => 'خط فروش پیش‌فرض', 'is_default' => true, 'is_active' => true, 'sort_order' => 1],
        );

        $stages = [
            ['name' => 'جدید', 'sort_order' => 1, 'probability' => 10, 'is_won' => false, 'is_lost' => false],
            ['name' => 'ارزیابی اولیه', 'sort_order' => 2, 'probability' => 20, 'is_won' => false, 'is_lost' => false],
            ['name' => 'تحلیل نیاز', 'sort_order' => 3, 'probability' => 30, 'is_won' => false, 'is_lost' => false],
            ['name' => 'جلسه', 'sort_order' => 4, 'probability' => 40, 'is_won' => false, 'is_lost' => false],
            ['name' => 'پیشنهاد', 'sort_order' => 5, 'probability' => 60, 'is_won' => false, 'is_lost' => false],
            ['name' => 'مذاکره', 'sort_order' => 6, 'probability' => 80, 'is_won' => false, 'is_lost' => false],
            ['name' => 'برنده', 'sort_order' => 7, 'probability' => 100, 'is_won' => true, 'is_lost' => false],
            ['name' => 'از دست رفته', 'sort_order' => 8, 'probability' => 0, 'is_won' => false, 'is_lost' => true],
        ];

        foreach ($stages as $stage) {
            PipelineStage::updateOrCreate(
                ['pipeline_id' => $pipeline->id, 'sort_order' => $stage['sort_order']],
                $stage + ['is_active' => true],
            );
        }
    }

    private function seedPermissions(): void
    {
        $permissions = [
            ['key' => 'crm.dashboard.view', 'title' => 'مشاهده داشبورد CRM', 'group' => 'CRM'],
            ['key' => 'crm.records.view_all', 'title' => 'مشاهده همه رکوردهای CRM', 'group' => 'CRM'],
            ['key' => 'crm.customers.view', 'title' => 'مشاهده مشتریان CRM', 'group' => 'CRM'],
            ['key' => 'crm.customers.assign', 'title' => 'تخصیص مسئول مشتری', 'group' => 'CRM'],
            ['key' => 'crm.contacts.view', 'title' => 'مشاهده مخاطبین CRM', 'group' => 'CRM'],
            ['key' => 'crm.contacts.create', 'title' => 'ایجاد مخاطب CRM', 'group' => 'CRM'],
            ['key' => 'crm.contacts.update', 'title' => 'ویرایش مخاطب CRM', 'group' => 'CRM'],
            ['key' => 'crm.contacts.delete', 'title' => 'حذف مخاطب CRM', 'group' => 'CRM'],
            ['key' => 'crm.leads.view', 'title' => 'مشاهده سرنخ‌ها', 'group' => 'CRM'],
            ['key' => 'crm.leads.create', 'title' => 'ایجاد سرنخ', 'group' => 'CRM'],
            ['key' => 'crm.leads.update', 'title' => 'ویرایش سرنخ', 'group' => 'CRM'],
            ['key' => 'crm.leads.delete', 'title' => 'حذف سرنخ', 'group' => 'CRM'],
            ['key' => 'crm.leads.convert', 'title' => 'تبدیل سرنخ', 'group' => 'CRM'],
            ['key' => 'crm.opportunities.view', 'title' => 'مشاهده فرصت‌ها', 'group' => 'CRM'],
            ['key' => 'crm.opportunities.create', 'title' => 'ایجاد فرصت', 'group' => 'CRM'],
            ['key' => 'crm.opportunities.update', 'title' => 'ویرایش فرصت', 'group' => 'CRM'],
            ['key' => 'crm.opportunities.delete', 'title' => 'حذف فرصت', 'group' => 'CRM'],
            ['key' => 'crm.opportunities.move_stage', 'title' => 'تغییر مرحله فرصت', 'group' => 'CRM'],
            ['key' => 'crm.activities.view', 'title' => 'مشاهده فعالیت‌ها', 'group' => 'CRM'],
            ['key' => 'crm.activities.create', 'title' => 'ایجاد فعالیت', 'group' => 'CRM'],
            ['key' => 'crm.activities.update', 'title' => 'ویرایش فعالیت', 'group' => 'CRM'],
            ['key' => 'crm.activities.delete', 'title' => 'حذف فعالیت', 'group' => 'CRM'],
            ['key' => 'crm.tasks.view', 'title' => 'مشاهده وظایف', 'group' => 'CRM'],
            ['key' => 'crm.tasks.create', 'title' => 'ایجاد وظیفه', 'group' => 'CRM'],
            ['key' => 'crm.tasks.update', 'title' => 'ویرایش وظیفه', 'group' => 'CRM'],
            ['key' => 'crm.tasks.delete', 'title' => 'حذف وظیفه', 'group' => 'CRM'],
            ['key' => 'crm.pipelines.manage', 'title' => 'مدیریت خط فروش', 'group' => 'CRM'],
            ['key' => 'crm.reports.view', 'title' => 'مشاهده گزارش‌های CRM', 'group' => 'CRM'],
            ['key' => 'crm.reports.export', 'title' => 'خروجی گزارش CRM', 'group' => 'CRM'],
            ['key' => 'crm.sold_devices.view', 'title' => 'مشاهده گارانتی', 'group' => 'CRM'],
            ['key' => 'crm.sold_devices.create', 'title' => 'ثبت گارانتی', 'group' => 'CRM'],
            ['key' => 'crm.sold_devices.update', 'title' => 'ویرایش گارانتی', 'group' => 'CRM'],
            ['key' => 'crm.sold_devices.delete', 'title' => 'حذف گارانتی', 'group' => 'CRM'],
            ['key' => 'crm.settings.manage', 'title' => 'تنظیمات CRM', 'group' => 'CRM'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['key' => $permission['key']], $permission);
        }

        $crmKeys = collect($permissions)->pluck('key');
        $allCrm = Permission::whereIn('key', $crmKeys)->pluck('id');

        $roles = [
            'crm_manager' => ['title' => 'مدیر CRM', 'description' => 'دسترسی کامل CRM', 'permissions' => $allCrm],
            'crm_sales' => ['title' => 'فروش CRM', 'description' => 'فروش و پیگیری', 'permissions' => Permission::whereIn('key', [
                'crm.dashboard.view', 'crm.customers.view', 'crm.contacts.view', 'crm.contacts.create', 'crm.contacts.update',
                'crm.leads.view', 'crm.leads.create', 'crm.leads.update', 'crm.leads.convert',
                'crm.opportunities.view', 'crm.opportunities.create', 'crm.opportunities.update', 'crm.opportunities.delete', 'crm.opportunities.move_stage',
                'crm.activities.view', 'crm.activities.create', 'crm.activities.update',
                'crm.tasks.view', 'crm.tasks.create', 'crm.tasks.update', 'crm.reports.view',
                'crm.sold_devices.view', 'crm.sold_devices.create', 'crm.sold_devices.update',
                'commerce.view',
            ])->pluck('id')],
            'crm_viewer' => ['title' => 'مشاهده‌گر CRM', 'description' => 'فقط مشاهده CRM', 'permissions' => Permission::whereIn('key', [
                'crm.dashboard.view', 'crm.customers.view', 'crm.contacts.view', 'crm.leads.view',
                'crm.opportunities.view', 'crm.activities.view', 'crm.tasks.view', 'crm.reports.view',
                'crm.sold_devices.view',
            ])->pluck('id')],
        ];

        foreach ($roles as $name => $data) {
            $role = Role::updateOrCreate(
                ['name' => $name],
                ['title' => $data['title'], 'description' => $data['description'], 'is_system' => false],
            );
            $role->permissions()->syncWithoutDetaching($data['permissions']);
        }

        Role::where('name', 'admin')->first()?->permissions()->syncWithoutDetaching(Permission::pluck('id'));
        Role::where('name', 'commercial_manager')->first()?->permissions()->syncWithoutDetaching(
            Permission::whereIn('key', $crmKeys)->pluck('id'),
        );
    }
}
