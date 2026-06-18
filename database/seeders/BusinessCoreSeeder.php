<?php

namespace Database\Seeders;

use App\Models\ChartAccount;
use App\Models\CompanySetting;
use App\Models\MeasurementUnit;
use App\Models\NumberingSetting;
use App\Models\PartyType;
use Illuminate\Database\Seeder;

class BusinessCoreSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'customer', 'title' => 'مشتری'],
            ['name' => 'vendor', 'title' => 'فروشنده'],
            ['name' => 'colleague', 'title' => 'همکار'],
            ['name' => 'marketer', 'title' => 'بازاریاب'],
            ['name' => 'contractor', 'title' => 'پیمانکار'],
        ] as $type) {
            PartyType::updateOrCreate(['name' => $type['name']], $type);
        }

        foreach ([
            ['code' => 'PCS', 'name' => 'عدد'],
            ['code' => 'KG', 'name' => 'کیلوگرم'],
            ['code' => 'M', 'name' => 'متر'],
            ['code' => 'HOUR', 'name' => 'ساعت'],
            ['code' => 'SERVICE', 'name' => 'خدمت'],
        ] as $unit) {
            MeasurementUnit::updateOrCreate(['code' => $unit['code']], $unit + ['is_active' => true]);
        }

        foreach ([
            ['document_key' => 'party', 'prefix' => 'P-', 'next_number' => 1, 'padding' => 5],
            ['document_key' => 'item', 'prefix' => 'I-', 'next_number' => 1, 'padding' => 5],
            ['document_key' => 'sale_proforma', 'prefix' => 'SP-', 'next_number' => 1, 'padding' => 5],
            ['document_key' => 'sale_invoice', 'prefix' => 'SI-', 'next_number' => 1, 'padding' => 5],
            ['document_key' => 'purchase_invoice', 'prefix' => 'PI-', 'next_number' => 1, 'padding' => 5],
            ['document_key' => 'inventory_receipt', 'prefix' => 'IR-', 'next_number' => 1, 'padding' => 5],
            ['document_key' => 'inventory_issue', 'prefix' => 'II-', 'next_number' => 1, 'padding' => 5],
            ['document_key' => 'inventory_transfer', 'prefix' => 'IT-', 'next_number' => 1, 'padding' => 5],
            ['document_key' => 'accounting_document', 'prefix' => 'ACC-', 'next_number' => 1, 'padding' => 5],
        ] as $setting) {
            NumberingSetting::updateOrCreate(['document_key' => $setting['document_key']], $setting);
        }

        CompanySetting::firstOrCreate([], [
            'company_name' => config('app.name', 'ERP'),
            'default_vat_rate' => 10,
        ]);

        $this->seedAccounts();
    }

    private function seedAccounts(): void
    {
        $accounts = [
            ['code' => '1', 'title' => 'دارایی‌ها', 'level' => 'group', 'nature' => 'debit', 'parent' => null],
            ['code' => '11', 'title' => 'دارایی‌های جاری', 'level' => 'ledger', 'nature' => 'debit', 'parent' => '1'],
            ['code' => '1101', 'title' => 'حساب‌های دریافتنی تجاری', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '11'],
            ['code' => '1102', 'title' => 'اعتبار مالیات ارزش افزوده خرید', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '11'],
            ['code' => '1103', 'title' => 'موجودی کالا', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '11'],
            ['code' => '12', 'title' => 'نقد و بانک', 'level' => 'ledger', 'nature' => 'debit', 'parent' => '1'],
            ['code' => '1201', 'title' => 'صندوق', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '12'],
            ['code' => '1202', 'title' => 'بانک', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '12'],
            ['code' => '2', 'title' => 'بدهی‌ها', 'level' => 'group', 'nature' => 'credit', 'parent' => null],
            ['code' => '21', 'title' => 'بدهی‌های جاری', 'level' => 'ledger', 'nature' => 'credit', 'parent' => '2'],
            ['code' => '2101', 'title' => 'حساب‌های پرداختنی تجاری', 'level' => 'subsidiary', 'nature' => 'credit', 'parent' => '21'],
            ['code' => '2102', 'title' => 'مالیات ارزش افزوده فروش', 'level' => 'subsidiary', 'nature' => 'credit', 'parent' => '21'],
            ['code' => '3', 'title' => 'حقوق مالکانه', 'level' => 'group', 'nature' => 'credit', 'parent' => null],
            ['code' => '31', 'title' => 'سرمایه و سود انباشته', 'level' => 'ledger', 'nature' => 'credit', 'parent' => '3'],
            ['code' => '3101', 'title' => 'سرمایه', 'level' => 'subsidiary', 'nature' => 'credit', 'parent' => '31'],
            ['code' => '4', 'title' => 'درآمدها', 'level' => 'group', 'nature' => 'credit', 'parent' => null],
            ['code' => '41', 'title' => 'درآمد عملیاتی', 'level' => 'ledger', 'nature' => 'credit', 'parent' => '4'],
            ['code' => '4101', 'title' => 'فروش کالا و خدمات', 'level' => 'subsidiary', 'nature' => 'credit', 'parent' => '41'],
            ['code' => '5', 'title' => 'هزینه‌ها و بهای تمام شده', 'level' => 'group', 'nature' => 'debit', 'parent' => null],
            ['code' => '51', 'title' => 'خرید و بهای تمام شده', 'level' => 'ledger', 'nature' => 'debit', 'parent' => '5'],
            ['code' => '5101', 'title' => 'خرید کالا و خدمات', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '51'],
        ];

        foreach ($accounts as $account) {
            $parentId = $account['parent']
                ? ChartAccount::where('code', $account['parent'])->value('id')
                : null;

            ChartAccount::updateOrCreate(
                ['code' => $account['code']],
                [
                    'parent_id' => $parentId,
                    'level' => $account['level'],
                    'title' => $account['title'],
                    'nature' => $account['nature'],
                    'is_active' => true,
                    'is_system' => true,
                ]
            );
        }
    }
}
