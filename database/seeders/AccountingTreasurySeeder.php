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
            ['key' => 'fiscal.view', 'title' => 'مشاهده سال مالی', 'group' => 'حسابداری'],
            ['key' => 'fiscal.close', 'title' => 'بستن سال مالی', 'group' => 'حسابداری'],
            ['key' => 'fiscal.open', 'title' => 'بازکردن سال مالی', 'group' => 'حسابداری'],
            ['key' => 'fiscal.reopen', 'title' => 'بازگشایی سال مالی', 'group' => 'حسابداری'],
            ['key' => 'fiscal.manage_sequences', 'title' => 'مدیریت شماره‌گذاری سال مالی', 'group' => 'حسابداری'],
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
            'fiscal.view',
            'fiscal.close',
            'fiscal.open',
            'fiscal.reopen',
            'fiscal.manage_sequences',
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
            ['code' => '32', 'title' => 'حساب‌های جاری شرکا', 'level' => 'ledger', 'nature' => 'credit', 'parent' => '3'],
            ['code' => '3201', 'title' => 'حساب جاری مهدی', 'level' => 'subsidiary', 'nature' => 'credit', 'parent' => '32'],
            ['code' => '320101', 'title' => 'برداشت‌های مهدی', 'level' => 'detail', 'nature' => 'credit', 'parent' => '3201'],
            ['code' => '320102', 'title' => 'واریزهای مهدی', 'level' => 'detail', 'nature' => 'credit', 'parent' => '3201'],
            ['code' => '3202', 'title' => 'حساب جاری میلاد', 'level' => 'subsidiary', 'nature' => 'credit', 'parent' => '32'],
            ['code' => '320201', 'title' => 'برداشت‌های میلاد', 'level' => 'detail', 'nature' => 'credit', 'parent' => '3202'],
            ['code' => '320202', 'title' => 'واریزهای میلاد', 'level' => 'detail', 'nature' => 'credit', 'parent' => '3202'],
            ['code' => '4102', 'title' => 'درآمد متفرقه', 'level' => 'subsidiary', 'nature' => 'credit', 'parent' => '41'],
            ['code' => '42', 'title' => 'درآمدهای غیرعملیاتی', 'level' => 'ledger', 'nature' => 'credit', 'parent' => '4'],
            ['code' => '4201', 'title' => 'درآمد سود سپرده', 'level' => 'subsidiary', 'nature' => 'credit', 'parent' => '42'],
            ['code' => '4202', 'title' => 'سود تسعیر ارز', 'level' => 'subsidiary', 'nature' => 'credit', 'parent' => '42'],
            ['code' => '4203', 'title' => 'درآمد متفرقه غیرعملیاتی', 'level' => 'subsidiary', 'nature' => 'credit', 'parent' => '42'],
            ['code' => '5102', 'title' => 'تعدیل موجودی انبار', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '51'],
            ['code' => '5103', 'title' => 'بهای تمام‌شده کالای فروش‌رفته', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '51'],
            ['code' => '510101', 'title' => 'خرید کالا', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5101'],
            ['code' => '510102', 'title' => 'خدمات پیمانکار', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5101'],
            ['code' => '510103', 'title' => 'تجهیزات پروژه', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5101'],
            ['code' => '510104', 'title' => 'خرید نرم‌افزار', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5101'],
            ['code' => '5105', 'title' => 'هزینه‌های مستقیم پروژه', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '51'],
            ['code' => '510501', 'title' => 'دستمزد پیمانکار', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5105'],
            ['code' => '510502', 'title' => 'حمل و نقل پروژه', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5105'],
            ['code' => '510503', 'title' => 'اسکان پروژه', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5105'],
            ['code' => '510504', 'title' => 'حمل تجهیزات', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5105'],
            ['code' => '510505', 'title' => 'هزینه نصب', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5105'],
            ['code' => '1105', 'title' => 'کالای ساخته‌شده', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '11'],
            ['code' => '1106', 'title' => 'کار در جریان ساخت', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '11'],
            ['code' => '1109', 'title' => 'سایر دارایی‌های جاری', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '11'],
            ['code' => '110901', 'title' => 'تنخواه', 'level' => 'detail', 'nature' => 'debit', 'parent' => '1109'],
            ['code' => '110902', 'title' => 'پیش‌پرداخت اجاره', 'level' => 'detail', 'nature' => 'debit', 'parent' => '1109'],
            ['code' => '110903', 'title' => 'پیش‌پرداخت بیمه', 'level' => 'detail', 'nature' => 'debit', 'parent' => '1109'],
            ['code' => '110904', 'title' => 'پیش‌پرداخت مالیات', 'level' => 'detail', 'nature' => 'debit', 'parent' => '1109'],
            ['code' => '110905', 'title' => 'مطالبات از شرکا', 'level' => 'detail', 'nature' => 'debit', 'parent' => '1109'],
            ['code' => '1110', 'title' => 'دارایی‌های ارزی', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '11'],
            ['code' => '111001', 'title' => 'دلار آمریکا', 'level' => 'detail', 'nature' => 'debit', 'parent' => '1110'],
            ['code' => '111002', 'title' => 'یورو', 'level' => 'detail', 'nature' => 'debit', 'parent' => '1110'],
            ['code' => '111003', 'title' => 'درهم امارات', 'level' => 'detail', 'nature' => 'debit', 'parent' => '1110'],
            ['code' => '1111', 'title' => 'سرمایه‌گذاری‌ها', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '11'],
            ['code' => '111101', 'title' => 'طلا', 'level' => 'detail', 'nature' => 'debit', 'parent' => '1111'],
            ['code' => '111102', 'title' => 'سکه', 'level' => 'detail', 'nature' => 'debit', 'parent' => '1111'],
            ['code' => '111103', 'title' => 'صندوق‌ها', 'level' => 'detail', 'nature' => 'debit', 'parent' => '1111'],
            ['code' => '2104', 'title' => 'تسویه حقوق و دستمزد تولید', 'level' => 'subsidiary', 'nature' => 'credit', 'parent' => '21'],
            ['code' => '5104', 'title' => 'جذب سربار تولید', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '51'],
            ['code' => '52', 'title' => 'هزینه‌های عملیاتی', 'level' => 'ledger', 'nature' => 'debit', 'parent' => '5'],
            ['code' => '5201', 'title' => 'هزینه عمومی', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '52'],
            ['code' => '520101', 'title' => 'اجاره', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5201'],
            ['code' => '520102', 'title' => 'ناهار پرسنل', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5201'],
            ['code' => '520103', 'title' => 'تنخواه', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5201'],
            ['code' => '520104', 'title' => 'پذیرایی', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5201'],
            ['code' => '520105', 'title' => 'خرید لوازم', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5201'],
            ['code' => '520106', 'title' => 'تعمیرات', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5201'],
            ['code' => '520107', 'title' => 'حمل و نقل', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5201'],
            ['code' => '520108', 'title' => 'سایر هزینه های روزمره', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5201'],
            ['code' => '5202', 'title' => 'هزینه حقوق و دستمزد', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '52'],
            ['code' => '520201', 'title' => 'حقوق پایه', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5202'],
            ['code' => '520202', 'title' => 'اضافه‌کاری', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5202'],
            ['code' => '520203', 'title' => 'مزایا', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5202'],
            ['code' => '520204', 'title' => 'پاداش', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5202'],
            ['code' => '520205', 'title' => 'سنوات', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5202'],
            ['code' => '520206', 'title' => 'بیمه کارفرما', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5202'],
            ['code' => '5203', 'title' => 'هزینه اداری', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '52'],
            ['code' => '520301', 'title' => 'اجاره', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5203'],
            ['code' => '520302', 'title' => 'آب', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5203'],
            ['code' => '520303', 'title' => 'برق', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5203'],
            ['code' => '520304', 'title' => 'گاز', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5203'],
            ['code' => '520305', 'title' => 'اینترنت', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5203'],
            ['code' => '520306', 'title' => 'تلفن', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5203'],
            ['code' => '520307', 'title' => 'پذیرایی', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5203'],
            ['code' => '520308', 'title' => 'غذا', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5203'],
            ['code' => '520309', 'title' => 'ملزومات اداری', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5203'],
            ['code' => '520310', 'title' => 'تنخواه', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5203'],
            ['code' => '5204', 'title' => 'هزینه حمل و نقل', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '52'],
            ['code' => '520401', 'title' => 'سوخت', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5204'],
            ['code' => '520402', 'title' => 'تاکسی', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5204'],
            ['code' => '520403', 'title' => 'تعمیرات خودرو', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5204'],
            ['code' => '520404', 'title' => 'بیمه خودرو', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5204'],
            ['code' => '5205', 'title' => 'هزینه بازاریابی', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '52'],
            ['code' => '520501', 'title' => 'تبلیغات', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5205'],
            ['code' => '520502', 'title' => 'وب‌سایت', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5205'],
            ['code' => '520503', 'title' => 'سئو', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5205'],
            ['code' => '520504', 'title' => 'شبکه‌های اجتماعی', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5205'],
            ['code' => '5206', 'title' => 'هزینه‌های مالی', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '52'],
            ['code' => '520601', 'title' => 'کارمزد بانکی', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5206'],
            ['code' => '520602', 'title' => 'جرایم', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5206'],
            ['code' => '520603', 'title' => 'دیرکرد', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5206'],
            ['code' => '5207', 'title' => 'استهلاک', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '52'],
            ['code' => '520701', 'title' => 'کامپیوتر', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5207'],
            ['code' => '520702', 'title' => 'سرور', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5207'],
            ['code' => '520703', 'title' => 'خودرو', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5207'],
            ['code' => '520704', 'title' => 'تجهیزات شبکه', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5207'],
            ['code' => '410201', 'title' => 'درآمد متفرقه', 'level' => 'detail', 'nature' => 'credit', 'parent' => '4102'],
            ['code' => '410202', 'title' => 'سایر درآمدهای غیر فاکتوری', 'level' => 'detail', 'nature' => 'credit', 'parent' => '4102'],
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
        $bank = BankAccount::withTrashed()->updateOrCreate(
            ['code' => 'BANK-001'],
            [
                'bank_name' => 'بانک اصلی',
                'currency' => 'IRR',
                'opening_balance' => 0,
                'chart_account_id' => ChartAccount::where('code', '1202')->value('id'),
                'is_active' => true,
            ]
        );
        $bank->restore();

        $cashbox = Cashbox::withTrashed()->updateOrCreate(
            ['code' => 'CASH-001'],
            [
                'name' => 'صندوق اصلی',
                'currency' => 'IRR',
                'opening_balance' => 0,
                'chart_account_id' => ChartAccount::where('code', '1201')->value('id'),
                'is_active' => true,
            ]
        );
        $cashbox->restore();
    }
}
