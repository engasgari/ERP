<?php

namespace Database\Seeders;

use App\Models\AccountingDocument;
use App\Models\AccountingDocumentLine;
use App\Models\AttendanceCalculation;
use App\Models\BankAccount;
use App\Models\BomLine;
use App\Models\BomVersion;
use App\Models\Cashbox;
use App\Models\ChartAccount;
use App\Models\CompanySetting;
use App\Models\CostCenter;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\EmploymentOrder;
use App\Models\FinancialTransaction;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\InventoryDocument;
use App\Models\InventoryDocumentLine;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Item;
use App\Models\Job;
use App\Models\MeasurementUnit;
use App\Models\MonthlyAttendance;
use App\Models\OrganizationUnit;
use App\Models\Party;
use App\Models\PartyType;
use App\Models\Payment;
use App\Models\PayrollCalculation;
use App\Models\PayrollCalculationLine;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\Permission;
use App\Models\Position;
use App\Models\ProductionOrder;
use App\Models\Project;
use App\Models\Role;
use App\Models\Salary;
use App\Models\SalaryLine;
use App\Models\TreasuryTransaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkCalendar;
use App\Models\WorkGroup;
use App\Models\WorkLog;
use App\Models\WorkShift;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ComprehensiveDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $user = $this->seedAccess();
            $this->seedCompany();
            $types = $this->seedPartyTypes();
            $units = $this->seedMeasurementUnits();
            $accounts = $this->seedChartAccounts();
            $fiscal = $this->seedFiscalYear();
            $parties = $this->seedParties($types);
            $warehouses = $this->seedWarehouses();
            $items = $this->seedItems($units);
            $projects = $this->seedProjects($parties, $user);
            $this->seedInventory($fiscal, $warehouses, $items, $projects, $user);
            $this->seedInvoices($fiscal, $parties, $items, $warehouses, $projects, $user);
            $this->seedTreasury($accounts, $parties, $projects, $user);
            $this->seedAccountingDocuments($fiscal, $accounts, $parties, $projects, $user);
            $hr = $this->seedHr($parties, $projects, $user);
            $this->seedAttendanceAndPayroll($hr['employees'], $projects, $user);
            $this->seedManufacturing($items, $projects, $user);
            $this->seedProjectTransactions($projects);
        });
    }

    private function seedAccess(): User
    {
        $permissions = [
            'users.manage' => ['مدیریت کاربران', 'دسترسی'],
            'base-info.view' => ['مشاهده اطلاعات پایه', 'اطلاعات پایه'],
            'base-info.manage' => ['مدیریت اطلاعات پایه', 'اطلاعات پایه'],
            'commerce.view' => ['مشاهده بازرگانی', 'بازرگانی'],
            'commerce.manage' => ['مدیریت بازرگانی', 'بازرگانی'],
            'accounting.view' => ['مشاهده حسابداری', 'مالی'],
            'accounting.manage' => ['مدیریت حسابداری', 'مالی'],
            'accounting.documents.create' => ['ایجاد سند حسابداری', 'مالی'],
            'accounting.documents.edit' => ['ویرایش سند حسابداری', 'مالی'],
            'accounting.documents.delete' => ['حذف سند حسابداری', 'مالی'],
            'accounting.documents.post' => ['ثبت قطعی سند حسابداری', 'مالی'],
            'financial.view' => ['مشاهده مالی', 'مالی'],
            'financial.manage' => ['مدیریت مالی', 'مالی'],
            'financial.reports.view' => ['مشاهده گزارش‌های مالی', 'گزارش‌ها'],
            'treasury.manage' => ['مدیریت خزانه', 'مالی'],
            'fiscal-years.manage' => ['مدیریت سال مالی', 'مالی'],
            'fiscal-periods.reopen' => ['بازگشایی دوره مالی', 'مالی'],
            'inventory.view' => ['مشاهده انبار پیشرفته', 'انبار'],
            'inventory.manage' => ['مدیریت انبار پیشرفته', 'انبار'],
            'warehouse.view' => ['مشاهده انبار', 'انبار'],
            'warehouse.manage' => ['مدیریت انبار', 'انبار'],
            'projects.view' => ['مشاهده پروژه‌ها', 'پروژه‌ها'],
            'projects.manage' => ['مدیریت پروژه‌ها', 'پروژه‌ها'],
            'employees.view' => ['مشاهده پرسنل', 'منابع انسانی'],
            'employees.manage' => ['مدیریت پرسنل', 'منابع انسانی'],
            'hr.view' => ['مشاهده منابع انسانی', 'منابع انسانی'],
            'hr.manage' => ['مدیریت منابع انسانی', 'منابع انسانی'],
            'employment-orders.view' => ['مشاهده احکام کارگزینی', 'منابع انسانی'],
            'employment-orders.manage' => ['مدیریت احکام کارگزینی', 'منابع انسانی'],
            'employment-orders.approve' => ['تایید احکام کارگزینی', 'منابع انسانی'],
            'contracts.view' => ['مشاهده قراردادها', 'منابع انسانی'],
            'contracts.manage' => ['مدیریت قراردادها', 'منابع انسانی'],
            'attendance.view' => ['مشاهده حضور و غیاب', 'حضور و غیاب'],
            'attendance.manage' => ['مدیریت حضور و غیاب', 'حضور و غیاب'],
            'attendance.import' => ['ورود اطلاعات تردد', 'حضور و غیاب'],
            'worklogs.view' => ['مشاهده کارکرد', 'کارکرد'],
            'worklogs.manage' => ['مدیریت کارکرد', 'کارکرد'],
            'salaries.view' => ['مشاهده حقوق', 'حقوق و دستمزد'],
            'salaries.manage' => ['مدیریت حقوق', 'حقوق و دستمزد'],
            'payroll.view' => ['مشاهده حقوق و دستمزد', 'حقوق و دستمزد'],
            'payroll.manage' => ['مدیریت حقوق و دستمزد', 'حقوق و دستمزد'],
            'payroll.approve' => ['تایید حقوق', 'حقوق و دستمزد'],
            'payroll.post' => ['صدور سند حقوق', 'حقوق و دستمزد'],
            'salary-payments.view' => ['مشاهده پرداخت حقوق', 'مالی'],
            'salary-payments.manage' => ['مدیریت پرداخت حقوق', 'مالی'],
            'settings.manage' => ['مدیریت تنظیمات', 'تنظیمات'],
            'reports.view' => ['مشاهده گزارش‌ها', 'گزارش‌ها'],
        ];

        foreach ($permissions as $key => [$title, $group]) {
            Permission::updateOrCreate(['key' => $key], compact('key', 'title', 'group'));
        }

        $admin = Role::updateOrCreate(
            ['name' => 'admin'],
            ['title' => 'مدیر سیستم', 'description' => 'دسترسی کامل به همه بخش‌ها', 'is_system' => true]
        );
        $admin->permissions()->sync(Permission::pluck('id'));

        $user = User::updateOrCreate(
            ['email' => 'admin@erp.test'],
            ['name' => 'مدیر سیستم', 'password' => Hash::make('password')]
        );
        $user->roles()->syncWithoutDetaching([$admin->id]);

        return $user;
    }

    private function seedCompany(): void
    {
        CompanySetting::firstOrCreate([], [
            'company_name' => 'شرکت بیکارن پایش آله',
            'default_vat_rate' => 10,
            'registration_number' => '14011661211',
            'postal_code' => '۱۴۱۵۵۱۳۳۷۷',
        ]);
    }

    private function seedPartyTypes(): array
    {
        $rows = [
            'customer' => 'مشتری',
            'vendor' => 'فروشنده',
            'employee' => 'پرسنل',
            'contractor' => 'پیمانکار',
            'shareholder' => 'سهامدار',
        ];

        foreach ($rows as $name => $title) {
            $types[$name] = PartyType::updateOrCreate(['name' => $name], compact('name', 'title'));
        }

        return $types ?? [];
    }

    private function seedMeasurementUnits(): array
    {
        $rows = [
            ['code' => 'PCS', 'name' => 'عدد'],
            ['code' => 'KG', 'name' => 'کیلوگرم'],
            ['code' => 'M', 'name' => 'متر'],
            ['code' => 'HOUR', 'name' => 'ساعت'],
            ['code' => 'SET', 'name' => 'ست'],
        ];

        foreach ($rows as $row) {
            $units[$row['code']] = MeasurementUnit::updateOrCreate(['code' => $row['code']], $row + ['is_active' => true]);
        }

        return $units ?? [];
    }

    private function seedChartAccounts(): array
    {
        $rows = [
            ['code' => '1', 'title' => 'دارایی‌ها', 'level' => 'group', 'nature' => 'debit'],
            ['code' => '11', 'title' => 'دارایی‌های جاری', 'level' => 'ledger', 'nature' => 'debit', 'parent' => '1'],
            ['code' => '1101', 'title' => 'حساب‌های دریافتنی تجاری', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '11'],
            ['code' => '1102', 'title' => 'موجودی کالا', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '11'],
            ['code' => '1103', 'title' => 'بانک', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '11'],
            ['code' => '2', 'title' => 'بدهی‌ها', 'level' => 'group', 'nature' => 'credit'],
            ['code' => '2101', 'title' => 'حساب‌های پرداختنی تجاری', 'level' => 'subsidiary', 'nature' => 'credit', 'parent' => '2'],
            ['code' => '3', 'title' => 'حقوق مالکانه', 'level' => 'group', 'nature' => 'credit'],
            ['code' => '4101', 'title' => 'فروش کالا و خدمات', 'level' => 'subsidiary', 'nature' => 'credit'],
            ['code' => '5101', 'title' => 'بهای تمام شده و هزینه‌ها', 'level' => 'subsidiary', 'nature' => 'debit'],
        ];

        foreach ($rows as $row) {
            $parentId = isset($row['parent']) ? ChartAccount::where('code', $row['parent'])->value('id') : null;
            $accounts[$row['code']] = ChartAccount::updateOrCreate(
                ['code' => $row['code']],
                [
                    'parent_id' => $parentId,
                    'level' => $row['level'],
                    'title' => $row['title'],
                    'nature' => $row['nature'],
                    'is_active' => true,
                    'is_system' => true,
                ]
            );
        }

        return $accounts ?? [];
    }

    private function seedFiscalYear(): array
    {
        $year = FiscalYear::updateOrCreate(
            ['jalali_year' => 1405],
            ['title' => 'سال مالی ۱۴۰۵', 'start_date' => '2026-03-21', 'end_date' => '2027-03-20', 'currency' => 'IRR', 'status' => 'open']
        );

        $period = FiscalPeriod::updateOrCreate(
            ['fiscal_year_id' => $year->id, 'period_number' => 3],
            ['title' => 'خرداد ۱۴۰۵', 'start_date' => '2026-05-22', 'end_date' => '2026-06-21', 'status' => 'open']
        );

        return ['year' => $year, 'period' => $period];
    }

    // private function seedParties(array $types): array
    // {
    //     $rows = [
    //         ['code' => 'PTY-DEMO-001', 'kind' => 'person', 'name' => 'لیلا احمدی', 'mobile' => '09120000001', 'national_id' => '0011111111', 'type' => 'employee'],
    //         ['code' => 'PTY-DEMO-002', 'kind' => 'person', 'name' => 'مهدی رضایی', 'mobile' => '09120000002', 'national_id' => '0011111112', 'type' => 'employee'],
    //         ['code' => 'PTY-DEMO-003', 'kind' => 'company', 'name' => 'شرکت سپهر صنعت', 'phone' => '02144000001', 'economic_code' => '411111111111', 'type' => 'customer'],
    //         ['code' => 'PTY-DEMO-004', 'kind' => 'company', 'name' => 'تامین قطعه پارس', 'phone' => '02144000002', 'economic_code' => '411111111112', 'type' => 'vendor'],
    //         ['code' => 'PTY-DEMO-005', 'kind' => 'company', 'name' => 'پیمانکاری سازه نو', 'phone' => '02144000003', 'economic_code' => '411111111113', 'type' => 'contractor'],
    //     ];

    //     foreach ($rows as $index => $row) {
    //         $type = $row['type'];
    //         unset($row['type']);
    //         $party = Party::updateOrCreate(
    //             ['code' => $row['code']],
    //             $row + [
    //                 'detail_code' => 'DL-' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
    //                 'email' => 'party' . ($index + 1) . '@erp.test',
    //                 'postal_code' => '۱۴۱۵۵۱۳۳۷' . $index,
    //                 'address' => 'تهران، خیابان نمونه، پلاک ' . ($index + 1),
    //                 'is_active' => true,
    //                 'notes' => 'داده نمایشی قابل ویرایش',
    //             ]
    //         );

    //         if (isset($types[$type])) {
    //             $party->types()->syncWithoutDetaching([$types[$type]->id]);
    //         }

    //         $parties[] = $party;
    //     }

    //     return $parties ?? [];
    // }

    // private function seedWarehouses(): array
    // {
    //     foreach (['انبار مرکزی', 'انبار مواد اولیه', 'انبار محصول نهایی', 'انبار قطعات الکترونیک', 'انبار خدمات و ابزار'] as $index => $name) {
    //         $warehouses[] = Warehouse::updateOrCreate(
    //             ['name' => $name],
    //             ['description' => 'انبار نمایشی شماره ' . ($index + 1), 'is_active' => true]
    //         );
    //     }

    //     return $warehouses ?? [];
    // }

    // private function seedItems(array $units): array
    // {
    //     $rows = [
    //         ['code' => 'ITM-DEMO-001', 'type' => 'product', 'name' => 'کنترلر صنعتی مدل A', 'category' => 'محصول نهایی', 'unit' => 'PCS', 'sale' => 18500000, 'buy' => 12500000],
    //         ['code' => 'ITM-DEMO-002', 'type' => 'product', 'name' => 'برد الکترونیکی تغذیه', 'category' => 'قطعات الکترونیک', 'unit' => 'PCS', 'sale' => 6200000, 'buy' => 3900000],
    //         ['code' => 'ITM-DEMO-003', 'type' => 'product', 'name' => 'شاسی مکانیکی آلومینیومی', 'category' => 'قطعات مکانیک', 'unit' => 'PCS', 'sale' => 4800000, 'buy' => 3100000],
    //         ['code' => 'ITM-DEMO-004', 'type' => 'service', 'name' => 'خدمات طراحی مهندسی', 'category' => 'خدمات', 'unit' => 'HOUR', 'sale' => 1500000, 'buy' => 0],
    //         ['code' => 'ITM-DEMO-005', 'type' => 'product', 'name' => 'ست کابل و کانکتور', 'category' => 'مواد مصرفی', 'unit' => 'SET', 'sale' => 2200000, 'buy' => 1450000],
    //     ];

    //     foreach ($rows as $row) {
    //         $items[] = Item::updateOrCreate(
    //             ['code' => $row['code']],
    //             [
    //                 'type' => $row['type'],
    //                 'name' => $row['name'],
    //                 'measurement_unit_id' => $units[$row['unit']]?->id ?? null,
    //                 'category' => $row['category'],
    //                 'sale_price' => $row['sale'],
    //                 'purchase_price' => $row['buy'],
    //                 'is_active' => true,
    //                 'description' => 'کالای نمایشی قابل ویرایش',
    //             ]
    //         );
    //     }

    //     return $items ?? [];
    // }

    // private function seedProjects(array $parties, User $user): array
    // {
    //     foreach (['تابلو کنترل خط مونتاژ', 'دستگاه تست برد', 'طراحی مکانیزم بسته‌بندی', 'بهینه‌سازی خط تولید', 'سامانه پایش انرژی'] as $index => $name) {
    //         $projects[] = Project::updateOrCreate(
    //             ['project_number' => 'PRJ-DEMO-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
    //             [
    //                 'name' => $name,
    //                 'party_id' => $parties[2]->id ?? null,
    //                 'project_manager_id' => $user->id,
    //                 'description' => 'پروژه نمایشی برای تست گردش کار',
    //                 'status' => ['planning', 'active', 'procurement', 'manufacturing', 'testing'][$index],
    //                 'start_date' => now()->subDays(30 - $index)->toDateString(),
    //                 'end_date' => now()->addDays(45 + $index)->toDateString(),
    //                 'budget' => 500000000 + ($index * 80000000),
    //                 'cost_center_code' => 'CC-' . (200 + $index),
    //             ]
    //         );
    //     }

    //     return $projects ?? [];
    // }

    // private function seedInventory(array $fiscal, array $warehouses, array $items, array $projects, User $user): void
    // {
    //     foreach (range(0, 4) as $index) {
    //         $doc = InventoryDocument::updateOrCreate(
    //             ['number' => 'INV-DEMO-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
    //             [
    //                 'fiscal_year_id' => $fiscal['year']->id,
    //                 'type' => $index % 2 === 0 ? 'receipt' : 'issue',
    //                 'document_date' => now()->subDays(10 - $index)->toDateString(),
    //                 'document_time' => '09:00:00',
    //                 'warehouse_id' => $warehouses[$index % count($warehouses)]->id,
    //                 'project_id' => $projects[$index % count($projects)]->id,
    //                 'entry_mode' => 'manual',
    //                 'status' => 'confirmed',
    //                 'description' => 'سند انبار نمایشی',
    //                 'created_by' => $user->id,
    //                 'confirmed_by' => $user->id,
    //                 'confirmed_at' => now(),
    //             ]
    //         );

    //         $item = $items[$index % count($items)];
    //         $quantity = 5 + $index;
    //         $price = (float) ($item->purchase_price ?: $item->sale_price);

    //         InventoryDocumentLine::updateOrCreate(
    //             ['inventory_document_id' => $doc->id, 'item_id' => $item->id],
    //             ['quantity' => $quantity, 'unit_price' => $price, 'line_total' => $quantity * $price, 'description' => 'ردیف نمایشی']
    //         );
    //     }
    // }

    private function seedInvoices(array $fiscal, array $parties, array $items, array $warehouses, array $projects, User $user): void
    {
        foreach (range(0, 4) as $index) {
            $item = $items[$index % count($items)];
            $quantity = 1 + $index;
            $subtotal = $quantity * (float) $item->sale_price;
            $tax = $subtotal * 0.10;

            $invoice = Invoice::updateOrCreate(
                ['number' => 'INV-SALE-DEMO-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
                [
                    'fiscal_year_id' => $fiscal['year']->id,
                    'direction' => 'sale',
                    'document_type' => 'invoice',
                    'invoice_date' => now()->subDays(5 - $index)->toDateString(),
                    'party_id' => $parties[2]->id ?? null,
                    'project_id' => $projects[$index % count($projects)]->id,
                    'warehouse_id' => $warehouses[$index % count($warehouses)]->id,
                    'status' => $index < 3 ? 'confirmed' : 'draft',
                    'subtotal' => $subtotal,
                    'discount_amount' => 0,
                    'tax_rate' => 10,
                    'tax_amount' => $tax,
                    'total_amount' => $subtotal + $tax,
                    'description' => 'فاکتور فروش نمایشی',
                    'created_by' => $user->id,
                    'confirmed_at' => $index < 3 ? now() : null,
                ]
            );

            InvoiceLine::updateOrCreate(
                ['invoice_id' => $invoice->id, 'item_id' => $item->id],
                [
                    'description' => $item->name,
                    'quantity' => $quantity,
                    'unit_price' => $item->sale_price,
                    'discount_amount' => 0,
                    'tax_rate' => 10,
                    'tax_amount' => $tax,
                    'line_total' => $subtotal + $tax,
                ]
            );
        }
    }

    private function seedTreasury(array $accounts, array $parties, array $projects, User $user): void
    {
        $bank = BankAccount::updateOrCreate(
            ['code' => 'BANK-DEMO-001'],
            ['bank_name' => 'بانک ملت', 'branch' => 'مرکزی', 'account_number' => '1234567890', 'iban' => 'IR000000000000000000000000', 'currency' => 'IRR', 'opening_balance' => 500000000, 'chart_account_id' => $accounts['1103']->id ?? null, 'is_active' => true]
        );

        $cashbox = Cashbox::updateOrCreate(
            ['code' => 'CASH-DEMO-001'],
            ['name' => 'صندوق مرکزی', 'currency' => 'IRR', 'opening_balance' => 50000000, 'chart_account_id' => $accounts['1103']->id ?? null, 'is_active' => true]
        );

        foreach (range(0, 4) as $index) {
            TreasuryTransaction::updateOrCreate(
                ['number' => 'TR-DEMO-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
                [
                    'type' => $index % 2 === 0 ? 'bank_receipt' : 'cash_payment',
                    'transaction_date' => now()->subDays($index)->toDateString(),
                    'amount' => 10000000 + ($index * 2500000),
                    'currency' => 'IRR',
                    'from_treasury_type' => $index % 2 === 0 ? null : Cashbox::class,
                    'from_treasury_id' => $index % 2 === 0 ? null : $cashbox->id,
                    'to_treasury_type' => $index % 2 === 0 ? BankAccount::class : null,
                    'to_treasury_id' => $index % 2 === 0 ? $bank->id : null,
                    'party_id' => $parties[$index % count($parties)]->id,
                    'project_id' => $projects[$index % count($projects)]->id,
                    'status' => 'draft',
                    'description' => 'تراکنش خزانه نمایشی',
                    'created_by' => $user->id,
                ]
            );
        }
    }

    private function seedAccountingDocuments(array $fiscal, array $accounts, array $parties, array $projects, User $user): void
    {
        foreach (range(0, 4) as $index) {
            $amount = 15000000 + ($index * 5000000);
            $doc = AccountingDocument::updateOrCreate(
                ['number' => 'ACC-DEMO-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
                [
                    'fiscal_year_id' => $fiscal['year']->id,
                    'fiscal_period_id' => $fiscal['period']->id,
                    'document_date' => now()->subDays($index)->toDateString(),
                    'type' => 'manual',
                    'status' => 'posted',
                    'currency' => 'IRR',
                    'description' => 'سند حسابداری نمایشی',
                    'created_by' => $user->id,
                    'posted_at' => now(),
                    'posted_by' => $user->id,
                ]
            );

            AccountingDocumentLine::updateOrCreate(
                ['accounting_document_id' => $doc->id, 'chart_account_id' => $accounts['1101']->id],
                ['party_id' => $parties[$index % count($parties)]->id, 'project_id' => $projects[$index % count($projects)]->id, 'description' => 'بدهکار نمایشی', 'debit' => $amount, 'credit' => 0, 'currency' => 'IRR', 'exchange_rate' => 1]
            );
            AccountingDocumentLine::updateOrCreate(
                ['accounting_document_id' => $doc->id, 'chart_account_id' => $accounts['4101']->id],
                ['party_id' => $parties[$index % count($parties)]->id, 'project_id' => $projects[$index % count($projects)]->id, 'description' => 'بستانکار نمایشی', 'debit' => 0, 'credit' => $amount, 'currency' => 'IRR', 'exchange_rate' => 1]
            );
        }
    }

    private function seedHr(array $parties, array $projects, User $user): array
    {
        foreach (['مدیریت عامل', 'پروژه', 'طراحی الکترونیک', 'طراحی مکانیک', 'تولید و مونتاژ'] as $index => $title) {
            $units[] = OrganizationUnit::updateOrCreate(
                ['code' => 'ORG-DEMO-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
                ['title' => $title, 'type' => $index === 0 ? 'company' : 'department', 'cost_center_code' => 'CC-HR-' . ($index + 1), 'is_active' => true, 'description' => 'واحد سازمانی نمایشی']
            );
        }

        foreach (['مدیرعامل', 'مدیر پروژه', 'مهندس الکترونیک', 'مهندس مکانیک', 'تکنسین مونتاژ'] as $index => $title) {
            $jobs[] = Job::updateOrCreate(
                ['code' => 'JOB-DEMO-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
                ['title' => $title, 'description' => 'شغل نمایشی', 'is_active' => true]
            );
        }

        foreach (range(0, 4) as $index) {
            $positions[] = Position::updateOrCreate(
                ['code' => 'POS-DEMO-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
                ['title' => $jobs[$index]->title, 'job_id' => $jobs[$index]->id, 'organization_unit_id' => $units[$index]->id, 'capacity' => $index === 4 ? 8 : 1, 'is_active' => true, 'description' => 'پست سازمانی نمایشی']
            );
        }

        $names = ['لیلا احمدی', 'مهدی رضایی', 'سارا کریمی', 'حامد مرادی', 'نگار شریفی'];
        foreach ($names as $index => $name) {
            $party = $parties[$index] ?? Party::updateOrCreate(
                ['code' => 'PTY-EMP-DEMO-' . ($index + 1)],
                ['kind' => 'person', 'name' => $name, 'mobile' => '0913000000' . $index, 'national_id' => '002222222' . $index, 'is_active' => true]
            );

            $employee = Employee::updateOrCreate(
                ['personnel_code' => 'EMP-DEMO-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
                [
                    'party_id' => $party->id,
                    'employee_code' => 'E' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                    'personnel_number' => 'PN-' . ($index + 1),
                    'attendance_card_number' => 'CARD-' . ($index + 1),
                    'hire_date' => '2026-03-21',
                    'first_name' => Str::before($name, ' '),
                    'last_name' => Str::after($name, ' '),
                    'national_code' => $party->national_id,
                    'department' => $units[$index]->title,
                    'position' => $positions[$index]->title,
                    'position_id' => $positions[$index]->id,
                    'organization_unit_id' => $units[$index]->id,
                    'employment_type' => 'full_time',
                    'salary_type' => 'monthly',
                    'salary' => 18000000 + ($index * 1000000),
                    'base_salary' => 16500000 + ($index * 1000000),
                    'hourly_rate' => 95000 + ($index * 5000),
                    'overtime_rate' => 140000 + ($index * 6000),
                    'default_cost_center' => 'CC-HR-' . ($index + 1),
                    'default_project_id' => $projects[$index % count($projects)]->id,
                    'insurance_number' => 'INS-' . ($index + 1),
                    'start_date' => '2026-03-21',
                    'is_active' => true,
                    'status' => 'active',
                    'notes' => 'پرسنل نمایشی',
                ]
            );

            $order = EmploymentOrder::updateOrCreate(
                ['number' => 'EO-DEMO-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
                [
                    'employee_id' => $employee->id,
                    'position_id' => $positions[$index]->id,
                    'job_id' => $jobs[$index]->id,
                    'organization_unit_id' => $units[$index]->id,
                    'cost_center_code' => 'CC-HR-' . ($index + 1),
                    'default_project_id' => $projects[$index % count($projects)]->id,
                    'order_type' => 'hire',
                    'employment_type' => 'full_time',
                    'insurance_status' => 'insured',
                    'effective_date' => '2026-03-21',
                    'base_salary' => $employee->base_salary,
                    'hourly_rate' => $employee->hourly_rate,
                    'housing_allowance' => 30000000,
                    'food_allowance' => 22000000,
                    'child_allowance' => $index % 2 === 0 ? 16625550 : 0,
                    'transportation_allowance' => 7000000,
                    'status' => 'approved',
                    'created_by' => $user->id,
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                    'notes' => 'حکم نمایشی تایید شده',
                ]
            );

            EmploymentContract::updateOrCreate(
                ['number' => 'CON-DEMO-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
                ['employee_id' => $employee->id, 'employment_order_id' => $order->id, 'contract_type' => 'fixed_term', 'start_date' => '2026-03-21', 'end_date' => '2027-03-20', 'body' => 'متن قرارداد نمایشی برای تست سیستم.', 'status' => 'active', 'created_by' => $user->id]
            );

            $employees[] = $employee;
        }

        return ['employees' => $employees ?? [], 'units' => $units ?? [], 'jobs' => $jobs ?? [], 'positions' => $positions ?? []];
    }

    private function seedAttendanceAndPayroll(array $employees, array $projects, User $user): void
    {
        $shift = WorkShift::updateOrCreate(
            ['code' => 'SHIFT-DEMO-001'],
            ['name' => 'شیفت عادی اداری', 'start_time' => '08:00', 'end_time' => '17:00', 'break_minutes' => 60, 'daily_work_hours' => 8, 'overtime_multiplier' => 1.4, 'late_tolerance_minutes' => 15, 'early_leave_tolerance_minutes' => 15, 'is_active' => true]
        );
        $calendar = WorkCalendar::updateOrCreate(
            ['code' => 'CAL-DEMO-1405'],
            ['name' => 'تقویم کاری ۱۴۰۵', 'jalali_year' => 1405, 'working_days' => [0, 1, 2, 3, 4], 'weekend_days' => [5, 6], 'holidays' => [], 'is_default' => true, 'is_active' => true]
        );
        $group = WorkGroup::updateOrCreate(
            ['code' => 'WG-DEMO-001'],
            ['name' => 'گروه کاری اداری', 'work_shift_id' => $shift->id, 'work_calendar_id' => $calendar->id, 'payroll_rules' => ['overtime_multiplier' => 1.4], 'is_active' => true]
        );
        $group->employees()->syncWithoutDetaching(collect($employees)->pluck('id')->all());

        $period = PayrollPeriod::updateOrCreate(
            ['year' => 1405, 'month' => 3],
            ['title' => 'خرداد ۱۴۰۵', 'starts_at' => '2026-05-22', 'ends_at' => '2026-06-21', 'status' => 'calculated', 'created_by' => $user->id, 'calculated_at' => now()]
        );

        $earning = PayrollItem::firstOrCreate(
            ['code' => 'demo_base_salary'],
            ['title' => 'حقوق پایه نمایشی', 'type' => 'earning', 'calculation_type' => 'fixed', 'default_amount' => 0, 'taxable' => true, 'insurable' => true, 'is_active' => true]
        );
        $deduction = PayrollItem::firstOrCreate(
            ['code' => 'demo_insurance'],
            ['title' => 'بیمه سهم کارمند نمایشی', 'type' => 'deduction', 'calculation_type' => 'percentage', 'default_rate' => 7, 'taxable' => false, 'insurable' => false, 'is_active' => true]
        );

        foreach ($employees as $index => $employee) {
            foreach (range(1, 5) as $day) {
                $hours = 8 + ($day === 2 ? 1.5 : 0);
                WorkLog::updateOrCreate(
                    ['employee_id' => $employee->id, 'work_date' => now()->startOfMonth()->addDays($day + $index)->toDateString(), 'project_id' => $projects[$index % count($projects)]->id],
                    [
                        'cost_center' => $employee->default_cost_center,
                        'check_in_time' => $day === 3 ? '08:25' : '08:00',
                        'check_out_time' => $day === 4 ? '16:30' : '17:00',
                        'start_time' => '08:00',
                        'end_time' => '17:00',
                        'hours' => $hours,
                        'overtime_hours' => $day === 2 ? 1.5 : 0,
                        'delay_hours' => $day === 3 ? 0.25 : 0,
                        'early_leave_hours' => $day === 4 ? 0.5 : 0,
                        'mission_hours' => $day === 5 ? 2 : 0,
                        'leave_hours' => 0,
                        'absence_hours' => 0,
                        'description' => 'کارکرد نمایشی',
                        'hourly_rate' => $employee->hourly_rate,
                        'total_amount' => $hours * (float) $employee->hourly_rate,
                        'attendance_source' => 'demo',
                        'import_batch' => 'DEMO-1405-03',
                    ]
                );
            }

            $gross = (float) $employee->base_salary + 5900000;
            $insurance = round($gross * 0.07);
            $tax = round(max($gross - 120000000, 0) * 0.10);
            $net = $gross - $insurance - $tax;

            $attendance = MonthlyAttendance::updateOrCreate(
                ['payroll_period_id' => $period->id, 'employee_id' => $employee->id],
                ['work_days' => 22, 'present_days' => 21, 'required_hours' => 176, 'normal_hours' => 168, 'overtime_hours' => 6 + $index, 'delay_hours' => 1, 'early_leave_hours' => 0.5, 'absence_hours' => 0, 'leave_hours' => 4, 'mission_hours' => 8, 'night_hours' => 0, 'holiday_hours' => 0, 'payable_hours' => 174 + $index, 'status' => 'approved', 'meta' => ['source' => 'demo']]
            );

            $calculation = PayrollCalculation::updateOrCreate(
                ['payroll_period_id' => $period->id, 'employee_id' => $employee->id],
                ['monthly_attendance_id' => $attendance->id, 'gross_salary' => $gross, 'total_benefits' => 5900000, 'insurance_employee' => $insurance, 'insurance_employer' => round($gross * 0.23), 'tax_amount' => $tax, 'total_deductions' => $insurance + $tax, 'net_payable' => $net, 'status' => 'calculated', 'calculated_at' => now()]
            );

            PayrollCalculationLine::updateOrCreate(
                ['payroll_calculation_id' => $calculation->id, 'code' => 'demo_base_salary'],
                ['payroll_item_id' => $earning->id, 'title' => 'حقوق پایه', 'type' => 'earning', 'hours' => 176, 'rate' => $employee->hourly_rate, 'amount' => $employee->base_salary, 'meta' => []]
            );
            PayrollCalculationLine::updateOrCreate(
                ['payroll_calculation_id' => $calculation->id, 'code' => 'demo_insurance'],
                ['payroll_item_id' => $deduction->id, 'title' => 'بیمه سهم کارمند', 'type' => 'deduction', 'hours' => 0, 'rate' => 7, 'amount' => $insurance, 'meta' => []]
            );

            Payslip::updateOrCreate(
                ['payroll_calculation_id' => $calculation->id],
                ['employee_id' => $employee->id, 'payroll_period_id' => $period->id, 'number' => 'PAYSLIP-DEMO-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT), 'issued_at' => now(), 'snapshot' => ['gross' => $gross, 'net' => $net], 'status' => 'issued']
            );

            $salary = Salary::updateOrCreate(
                ['employee_id' => $employee->id, 'year' => 1405, 'month' => 3],
                ['payroll_period_id' => $period->id, 'total_hours' => 174 + $index, 'hourly_rate' => $employee->hourly_rate, 'base_salary' => $employee->base_salary, 'overtime_hours' => 6 + $index, 'overtime_rate' => $employee->overtime_rate, 'overtime_salary' => (6 + $index) * (float) $employee->overtime_rate, 'bonus' => 500000, 'benefits' => 5900000, 'gross_salary' => $gross, 'deduction' => 0, 'insurance_amount' => $insurance, 'tax_amount' => $tax, 'loan_amount' => 0, 'penalty_amount' => 0, 'total_deductions' => $insurance + $tax, 'net_salary' => $net, 'advance_payment' => 0, 'final_salary' => $net, 'status' => 'calculated', 'notes' => 'حقوق نمایشی خرداد ۱۴۰۵']
            );

            SalaryLine::updateOrCreate(
                ['salary_id' => $salary->id, 'code' => 'demo_base_salary'],
                ['payroll_item_id' => $earning->id, 'title' => 'حقوق پایه', 'type' => 'earning', 'amount' => $employee->base_salary, 'meta' => []]
            );
            Payment::updateOrCreate(
                ['salary_id' => $salary->id, 'reference_number' => 'PAY-DEMO-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
                ['employee_id' => $employee->id, 'amount' => round($net / 2), 'payment_date' => now()->toDateString(), 'payment_method' => 'bank', 'description' => 'پرداخت علی‌الحساب نمایشی']
            );

            AttendanceCalculation::updateOrCreate(
                ['payroll_period_id' => $period->id, 'employee_id' => $employee->id, 'project_id' => $projects[$index % count($projects)]->id],
                ['normal_hours' => 168, 'overtime_hours' => 6 + $index, 'delay_hours' => 1, 'early_leave_hours' => 0.5, 'mission_hours' => 8, 'absence_hours' => 0, 'leave_hours' => 4, 'holiday_hours' => 0, 'night_hours' => 0, 'payable_hours' => 174 + $index, 'hourly_rate' => $employee->hourly_rate, 'labor_cost' => $net, 'status' => 'approved', 'meta' => ['source' => 'demo']]
            );
        }
    }

    private function seedManufacturing(array $items, array $projects, User $user): void
    {
        $finished = $items[0];

        foreach (range(1, 5) as $index) {
            $bom = BomVersion::updateOrCreate(
                ['item_id' => $finished->id, 'version_number' => 'DEMO-' . $index],
                ['revision' => 'R' . $index, 'status' => $index === 1 ? 'active' : 'draft', 'effective_date' => now()->subDays($index)->toDateString(), 'notes' => 'BOM نمایشی', 'created_by' => $user->id]
            );

            $component = $items[$index % count($items)];
            BomLine::updateOrCreate(
                ['bom_version_id' => $bom->id, 'component_item_id' => $component->id],
                ['quantity' => 1 + $index, 'measurement_unit_id' => $component->measurement_unit_id, 'waste_percentage' => 2, 'notes' => 'مصرف نمایشی']
            );

            ProductionOrder::updateOrCreate(
                ['number' => 'PROD-DEMO-' . str_pad((string) $index, 3, '0', STR_PAD_LEFT)],
                ['project_id' => $projects[($index - 1) % count($projects)]->id, 'item_id' => $finished->id, 'bom_version_id' => $bom->id, 'quantity' => 2 + $index, 'planned_start_date' => now()->addDays($index)->toDateString(), 'planned_end_date' => now()->addDays($index + 10)->toDateString(), 'status' => ['draft', 'approved', 'in_progress', 'testing', 'completed'][$index - 1], 'description' => 'دستور تولید نمایشی', 'created_by' => $user->id]
            );
        }
    }

    private function seedProjectTransactions(array $projects): void
    {
        foreach ($projects as $index => $project) {
            $income = FinancialTransaction::where('reference_number', 'FT-IN-DEMO-' . ($index + 1))->first() ?? new FinancialTransaction();
            $income->id ??= 900001 + $index;
            $income->fill([
                'project_id' => $project->id,
                'reference_number' => 'FT-IN-DEMO-' . ($index + 1),
                'type' => 'income',
                'category' => 'درآمد پروژه',
                'amount' => 100000000 + ($index * 50000000),
                'transaction_date' => now()->subDays($index)->toDateString(),
                'description' => 'درآمد نمایشی',
            ])->save();

            $expense = FinancialTransaction::where('reference_number', 'FT-EX-DEMO-' . ($index + 1))->first() ?? new FinancialTransaction();
            $expense->id ??= 900101 + $index;
            $expense->fill([
                'project_id' => $project->id,
                'reference_number' => 'FT-EX-DEMO-' . ($index + 1),
                'type' => 'expense',
                'category' => 'هزینه پروژه',
                'amount' => 40000000 + ($index * 10000000),
                'transaction_date' => now()->subDays($index)->toDateString(),
                'description' => 'هزینه نمایشی',
            ])->save();
        }
    }
}
