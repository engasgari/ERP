<?php

namespace App\Console\Commands;

use App\Models\AccountingDocumentLine;
use App\Models\ChartAccount;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\PartyType;
use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use App\Models\TreasuryTransaction;
use App\Services\NumberingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SplitEmployeeContractorParties extends Command
{
    protected $signature = 'parties:split-employee-contractor
        {--employee= : شناسه پرسنل (اختیاری)}
        {--dry-run : فقط گزارش بدون ذخیره}';

    protected $description = 'جداسازی طرف حساب پرسنل و پیمانکار برای افرادی که هر دو نقش دارند';

    /** @var list<array{first_name:string,last_name:string,contractor_suffix:string}> */
    private const TARGETS = [
        ['first_name' => 'حمید', 'last_name' => 'علیزاده', 'contractor_suffix' => 'پیمانکار'],
        ['first_name' => 'محسن', 'last_name' => 'اخلاصی', 'contractor_suffix' => 'پیمانکار'],
    ];

    public function handle(NumberingService $numbering): int
    {
        $dryRun = (bool) $this->option('dry-run');
        PartyType::ensureDefaults();

        $employeeFilter = $this->option('employee');
        $targets = self::TARGETS;

        if ($employeeFilter) {
            $employee = Employee::find($employeeFilter);
            if (! $employee) {
                $this->error("پرسنل #{$employeeFilter} یافت نشد.");

                return self::FAILURE;
            }
            $targets = array_values(array_filter(
                $targets,
                fn (array $target): bool => $employee->first_name === $target['first_name']
                    && $employee->last_name === $target['last_name']
            ));
        }

        foreach ($targets as $target) {
            $employee = Employee::query()
                ->where('first_name', $target['first_name'])
                ->where('last_name', $target['last_name'])
                ->with('party.types')
                ->first();

            if (! $employee || ! $employee->party_id) {
                $this->warn("پرسنل {$target['first_name']} {$target['last_name']} یا طرف حسابش یافت نشد؛ رد شد.");

                continue;
            }

            $result = $dryRun
                ? $this->previewSplit($employee, $target['contractor_suffix'])
                : $this->executeSplit($employee, $target['contractor_suffix'], $numbering);

            $this->line(sprintf(
                '%s %s → پرسنل party#%d | پیمانکار party#%s | فاکتور:%d | خط سند:%d | خزانه:%d',
                $employee->first_name,
                $employee->last_name,
                $employee->party_id,
                $result['contractor_party_id'] ?? 'جدید',
                $result['invoices'],
                $result['accounting_lines'],
                $result['treasury'],
            ));
        }

        if ($dryRun) {
            $this->warn('dry-run فعال است؛ تغییری ذخیره نشد.');
        } else {
            $this->info('جداسازی طرف حساب پرسنل/پیمانکار انجام شد.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{contractor_party_id?: int, invoices: int, accounting_lines: int, treasury: int}
     */
    private function previewSplit(Employee $employee, string $contractorSuffix): array
    {
        $employeePartyId = (int) $employee->party_id;
        $payrollAccountIds = $this->payrollAccountIds();
        $contractorName = trim($employee->first_name . ' ' . $employee->last_name . ' ' . $contractorSuffix);

        $this->info("--- پیش‌نمایش: {$employee->first_name} {$employee->last_name} → {$contractorName} ---");

        return [
            'invoices' => Invoice::where('party_id', $employeePartyId)->count(),
            'accounting_lines' => AccountingDocumentLine::where('party_id', $employeePartyId)
                ->whereNotIn('chart_account_id', $payrollAccountIds)
                ->count(),
            'treasury' => TreasuryTransaction::where('party_id', $employeePartyId)->count(),
        ];
    }

    /**
     * @return array{contractor_party_id: int, invoices: int, accounting_lines: int, treasury: int}
     */
    private function executeSplit(Employee $employee, string $contractorSuffix, NumberingService $numbering): array
    {
        return DB::transaction(function () use ($employee, $contractorSuffix, $numbering): array {
            $employeeParty = Party::with('types')->findOrFail($employee->party_id);
            $contractorName = trim($employee->first_name . ' ' . $employee->last_name . ' ' . $contractorSuffix);
            $payrollAccountIds = $this->payrollAccountIds();

            $contractorParty = Party::query()
                ->where('name', $contractorName)
                ->first();

            if (! $contractorParty) {
                $contractorParty = Party::create([
                    'code' => $numbering->next('party', 'P-'),
                    'detail_code' => $numbering->next('party_detail', 'D-'),
                    'kind' => $employeeParty->kind ?: 'person',
                    'name' => $contractorName,
                    'economic_code' => $employeeParty->economic_code,
                    'national_id' => $employeeParty->national_id,
                    'phone' => $employeeParty->phone,
                    'mobile' => $employeeParty->mobile,
                    'email' => $employeeParty->email,
                    'postal_code' => $employeeParty->postal_code,
                    'address' => $employeeParty->address,
                    'is_active' => true,
                    'notes' => 'طرف حساب پیمانکاری جدا از پرسنل #' . $employee->id,
                ]);
            }

            $vendorType = PartyType::where('name', 'vendor')->first();
            $contractorType = PartyType::where('name', 'contractor')->first();
            $colleagueType = PartyType::where('name', 'colleague')->first();

            $contractorTypeIds = collect([$vendorType?->id, $contractorType?->id])->filter()->all();
            $contractorParty->types()->sync($contractorTypeIds);

            $employeeTypeIds = collect([$colleagueType?->id])->filter()->all();
            $employeeParty->types()->sync($employeeTypeIds);
            $employeeParty->update([
                'notes' => trim(($employeeParty->notes ?: '') . ' | طرف حساب حقوق و دستمزد پرسنل'),
            ]);

            $invoiceCount = Invoice::where('party_id', $employeeParty->id)
                ->update(['party_id' => $contractorParty->id]);

            $lineCount = AccountingDocumentLine::where('party_id', $employeeParty->id)
                ->whereNotIn('chart_account_id', $payrollAccountIds)
                ->update(['party_id' => $contractorParty->id]);

            $treasuryCount = TreasuryTransaction::withTrashed()
                ->where('party_id', $employeeParty->id)
                ->update(['party_id' => $contractorParty->id]);

            $paymentCount = PaymentVoucher::where('party_id', $employeeParty->id)
                ->update(['party_id' => $contractorParty->id]);

            $receiptCount = ReceiptVoucher::where('party_id', $employeeParty->id)
                ->update(['party_id' => $contractorParty->id]);

            $remainingPayrollLines = AccountingDocumentLine::where('party_id', $employeeParty->id)
                ->whereIn('chart_account_id', $payrollAccountIds)
                ->count();

            $this->line("  contractor party#{$contractorParty->id} ({$contractorParty->code})");
            $this->line("  payment vouchers moved: {$paymentCount} | receipt vouchers: {$receiptCount}");
            $this->line("  payroll lines kept on employee party: {$remainingPayrollLines}");

            return [
                'contractor_party_id' => $contractorParty->id,
                'invoices' => $invoiceCount,
                'accounting_lines' => $lineCount,
                'treasury' => $treasuryCount,
            ];
        });
    }

    /**
     * @return array<int, int>
     */
    private function payrollAccountIds(): array
    {
        $roots = ChartAccount::query()
            ->where('code', '2104')
            ->orWhere('code', 'like', '2104%')
            ->pluck('id');

        $ids = collect();
        foreach ($roots as $rootId) {
            $ids = $ids->merge($this->accountAndDescendantIds((int) $rootId));
        }

        return $ids->unique()->values()->all();
    }

    /**
     * @return array<int, int>
     */
    private function accountAndDescendantIds(int $accountId): array
    {
        $ids = [$accountId];
        $frontier = [$accountId];

        while ($frontier) {
            $children = ChartAccount::whereIn('parent_id', $frontier)->pluck('id')->all();
            $children = array_values(array_diff($children, $ids));
            if ($children === []) {
                break;
            }
            $ids = array_merge($ids, $children);
            $frontier = $children;
        }

        return $ids;
    }
}
