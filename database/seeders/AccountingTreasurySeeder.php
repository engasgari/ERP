<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\Cashbox;
use App\Models\ChartAccount;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\NumberingSetting;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AccountingTreasurySeeder extends Seeder
{
    public function run(): void
    {
        $this->permissions();
        $this->numbering();
        $this->accounts();
        $this->fiscalCalendar();
        $this->treasury();
    }

    private function permissions(): void
    {
        $permissions = [
            ['key' => 'accounting.documents.create', 'title' => 'ایجاد سند حسابداری', 'group' => 'حسابداری'],
            ['key' => 'accounting.documents.edit', 'title' => 'ویرایش سند حسابداری', 'group' => 'حسابداری'],
            ['key' => 'accounting.documents.delete', 'title' => 'حذف سند حسابداری', 'group' => 'حسابداری'],
            ['key' => 'accounting.documents.post', 'title' => 'ثبت قطعی سند حسابداری', 'group' => 'حسابداری'],
            ['key' => 'fiscal-periods.reopen', 'title' => 'بازگشایی دوره مالی بسته‌شده', 'group' => 'حسابداری'],
            ['key' => 'treasury.manage', 'title' => 'مدیریت خزانه', 'group' => 'خزانه‌داری'],
            ['key' => 'financial.reports.view', 'title' => 'مشاهده گزارش‌های مالی', 'group' => 'گزارش‌ها'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['key' => $permission['key']], $permission);
        }

        $managerPermissions = Permission::whereIn('key', [
            'accounting.view',
            'accounting.manage',
            'accounting.documents.create',
            'accounting.documents.edit',
            'accounting.documents.delete',
            'accounting.documents.post',
            'financial.view',
            'financial.manage',
            'financial.reports.view',
            'reports.view',
            'fiscal-years.manage',
            'fiscal-periods.reopen',
            'treasury.manage',
        ])->pluck('id');

        foreach (['admin', 'accounting_manager', 'accountant'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->permissions()->syncWithoutDetaching($managerPermissions);
            }
        }
    }

    private function numbering(): void
    {
        foreach ([
            ['document_key' => 'treasury_transaction', 'prefix' => 'TR-', 'next_number' => 1, 'padding' => 5],
            ['document_key' => 'receipt_voucher', 'prefix' => 'RV-', 'next_number' => 1, 'padding' => 5],
            ['document_key' => 'payment_voucher', 'prefix' => 'PV-', 'next_number' => 1, 'padding' => 5],
        ] as $setting) {
            NumberingSetting::updateOrCreate(['document_key' => $setting['document_key']], $setting);
        }
    }

    private function accounts(): void
    {
        $accounts = [
            ['code' => '1104', 'title' => 'مانده اول دوره دریافتنی', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '11'],
            ['code' => '2103', 'title' => 'مانده اول دوره پرداختنی', 'level' => 'subsidiary', 'nature' => 'credit', 'parent' => '21'],
            ['code' => '3102', 'title' => 'سود و زیان انباشته', 'level' => 'subsidiary', 'nature' => 'credit', 'parent' => '31'],
            ['code' => '4102', 'title' => 'درآمد متفرقه', 'level' => 'subsidiary', 'nature' => 'credit', 'parent' => '41'],
            ['code' => '5102', 'title' => 'تعدیل موجودی انبار', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '51'],
            ['code' => '5103', 'title' => 'بهای تمام‌شده کالای فروش‌رفته', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '51'],
            ['code' => '1105', 'title' => 'کالای ساخته‌شده', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '11'],
            ['code' => '1106', 'title' => 'کار در جریان ساخت', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '11'],
            ['code' => '2104', 'title' => 'تسویه حقوق و دستمزد تولید', 'level' => 'subsidiary', 'nature' => 'credit', 'parent' => '21'],
            ['code' => '5104', 'title' => 'جذب سربار تولید', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '51'],
            ['code' => '52', 'title' => 'هزینه‌های عملیاتی', 'level' => 'ledger', 'nature' => 'debit', 'parent' => '5'],
            ['code' => '5201', 'title' => 'هزینه عمومی', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '52'],
            ['code' => '5202', 'title' => 'هزینه حقوق و دستمزد', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '52'],
            ['code' => '5203', 'title' => 'هزینه اداری', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '52'],
            ['code' => '5204', 'title' => 'هزینه حمل و نقل', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '52'],
        ];

        foreach ($accounts as $account) {
            ChartAccount::updateOrCreate(
                ['code' => $account['code']],
                [
                    'parent_id' => $account['parent'] ? ChartAccount::where('code', $account['parent'])->value('id') : null,
                    'level' => $account['level'],
                    'title' => $account['title'],
                    'nature' => $account['nature'],
                    'is_active' => true,
                    'is_system' => true,
                ]
            );
        }
    }

    private function fiscalCalendar(): void
    {
        $year = FiscalYear::updateOrCreate(
            ['jalali_year' => 1405],
            [
                'title' => 'سال مالی ۱۴۰۵',
                'start_date' => '2026-03-21',
                'end_date' => '2027-03-20',
                'currency' => 'IRR',
                'status' => 'open',
            ]
        );

        $starts = [
            '2026-03-21', '2026-04-21', '2026-05-22', '2026-06-22',
            '2026-07-23', '2026-08-23', '2026-09-23', '2026-10-23',
            '2026-11-22', '2026-12-22', '2027-01-21', '2027-02-20',
        ];
        $ends = [
            '2026-04-20', '2026-05-21', '2026-06-21', '2026-07-22',
            '2026-08-22', '2026-09-22', '2026-10-22', '2026-11-21',
            '2026-12-21', '2027-01-20', '2027-02-19', '2027-03-20',
        ];

        for ($i = 1; $i <= 12; $i++) {
            FiscalPeriod::updateOrCreate(
                ['fiscal_year_id' => $year->id, 'period_number' => $i],
                [
                    'title' => 'دوره ' . $i,
                    'start_date' => $starts[$i - 1],
                    'end_date' => $ends[$i - 1],
                    'status' => 'open',
                ]
            );
        }
    }

    private function treasury(): void
    {
        BankAccount::updateOrCreate(
            ['code' => 'BANK-001'],
            [
                'bank_name' => 'بانک اصلی',
                'currency' => 'IRR',
                'opening_balance' => 0,
                'chart_account_id' => ChartAccount::where('code', '1202')->value('id'),
                'is_active' => true,
            ]
        );

        Cashbox::updateOrCreate(
            ['code' => 'CASH-001'],
            [
                'name' => 'صندوق اصلی',
                'currency' => 'IRR',
                'opening_balance' => 0,
                'chart_account_id' => ChartAccount::where('code', '1201')->value('id'),
                'is_active' => true,
            ]
        );
    }
}
