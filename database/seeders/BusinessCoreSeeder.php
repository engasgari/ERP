<?php

namespace Database\Seeders;

use App\Models\ChartAccount;
use App\Models\CompanySetting;
use App\Models\MeasurementUnit;
use App\Models\NumberingSetting;
use App\Models\PartyType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class BusinessCoreSeeder extends Seeder
{
    public function run(): void
    {
        PartyType::ensureDefaults();

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
            ['document_key' => 'party', 'label' => 'کد طرف حساب', 'prefix' => 'P-', 'next_number' => 1, 'padding' => 5, 'sort_order' => 10],
            ['document_key' => 'party_detail', 'label' => 'کد تفصیل طرف حساب', 'prefix' => 'D-', 'next_number' => 1, 'padding' => 5, 'sort_order' => 20],
            ['document_key' => 'item', 'label' => 'کد کالا/خدمت', 'prefix' => 'I-', 'next_number' => 1, 'padding' => 5, 'sort_order' => 30],
            ['document_key' => 'project', 'label' => 'شماره پروژه', 'prefix' => 'PRJ-', 'next_number' => 1, 'padding' => 5, 'sort_order' => 40],
            ['document_key' => 'production_order', 'label' => 'شماره سفارش تولید', 'prefix' => 'PO-', 'next_number' => 1, 'padding' => 5, 'sort_order' => 50],
            ['document_key' => 'sale_proforma', 'label' => 'پیش‌فاکتور فروش', 'prefix' => 'SP-', 'next_number' => 1, 'padding' => 5, 'sort_order' => 110],
            ['document_key' => 'sale_invoice', 'label' => 'فاکتور فروش', 'prefix' => 'SI-', 'next_number' => 1, 'padding' => 5, 'sort_order' => 120],
            ['document_key' => 'purchase_invoice', 'label' => 'فاکتور خرید', 'prefix' => 'PI-', 'next_number' => 1, 'padding' => 5, 'sort_order' => 130],
            ['document_key' => 'inventory_receipt', 'label' => 'رسید انبار', 'prefix' => 'IR-', 'next_number' => 1, 'padding' => 5, 'sort_order' => 210],
            ['document_key' => 'inventory_issue', 'label' => 'حواله انبار', 'prefix' => 'II-', 'next_number' => 1, 'padding' => 5, 'sort_order' => 220],
            ['document_key' => 'inventory_transfer', 'label' => 'انتقال انبار', 'prefix' => 'IT-', 'next_number' => 1, 'padding' => 5, 'sort_order' => 230],
            ['document_key' => 'inventory_closing', 'label' => 'بستن موجودی انبار', 'prefix' => 'IC-', 'next_number' => 1, 'padding' => 5, 'sort_order' => 240],
            ['document_key' => 'inventory_opening', 'label' => 'افتتاحیه انبار', 'prefix' => 'IO-', 'next_number' => 1, 'padding' => 5, 'sort_order' => 250],
            ['document_key' => 'inventory_consumption', 'label' => 'مصرف مواد', 'prefix' => 'IC-', 'next_number' => 1, 'padding' => 5, 'sort_order' => 260],
            ['document_key' => 'accounting_document', 'label' => 'سند حسابداری', 'prefix' => 'ACC-', 'next_number' => 1, 'padding' => 5, 'sort_order' => 310],
        ] as $setting) {
            $payload = [
                'prefix' => $setting['prefix'],
                'next_number' => $setting['next_number'],
                'padding' => $setting['padding'],
            ];

            if (Schema::hasColumn('numbering_settings', 'label')) {
                $payload['label'] = $setting['label'];
            }

            if (Schema::hasColumn('numbering_settings', 'sort_order')) {
                $payload['sort_order'] = $setting['sort_order'];
            }

            if (Schema::hasColumn('numbering_settings', 'reuse_deleted_numbers')) {
                $payload['reuse_deleted_numbers'] = false;
            }

            NumberingSetting::updateOrCreate(['document_key' => $setting['document_key']], $payload);
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
