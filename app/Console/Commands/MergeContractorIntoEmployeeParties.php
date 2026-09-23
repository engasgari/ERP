<?php

namespace App\Console\Commands;

use App\Models\AccountingDocumentLine;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\PartyType;
use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use App\Models\TreasuryTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MergeContractorIntoEmployeeParties extends Command
{
    protected $signature = 'parties:merge-contractor-back
        {--employee= : شناسه پرسنل (اختیاری)}
        {--dry-run : فقط گزارش بدون ذخیره}';

    protected $description = 'ادغام طرف حساب پیمانکار جدا شده با طرف حساب اصلی پرسنل';

    /** @var list<array{first_name:string,last_name:string,contractor_suffix:string}> */
    private const TARGETS = [
        ['first_name' => 'حمید', 'last_name' => 'علیزاده', 'contractor_suffix' => 'پیمانکار'],
        ['first_name' => 'محسن', 'last_name' => 'اخلاصی', 'contractor_suffix' => 'پیمانکار'],
    ];

    public function handle(): int
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

            $contractorName = trim($employee->first_name . ' ' . $employee->last_name . ' ' . $target['contractor_suffix']);
            $contractorParty = Party::query()->where('name', $contractorName)->first();

            if (! $contractorParty) {
                $this->line("{$employee->first_name} {$employee->last_name}: طرف حساب «{$contractorName}» یافت نشد؛ رد شد.");

                continue;
            }

            if ((int) $contractorParty->id === (int) $employee->party_id) {
                $this->line("{$employee->first_name} {$employee->last_name}: طرف حساب پرسنل و پیمانکار یکسان است؛ رد شد.");

                continue;
            }

            $result = $dryRun
                ? $this->previewMerge($employee, $contractorParty)
                : $this->executeMerge($employee, $contractorParty);

            $this->line(sprintf(
                '%s %s → party#%d | از contractor#%d | فاکتور:%d | خط سند:%d | خزانه:%d | حذف contractor',
                $employee->first_name,
                $employee->last_name,
                $employee->party_id,
                $contractorParty->id,
                $result['invoices'],
                $result['accounting_lines'],
                $result['treasury'],
            ));
        }

        if ($dryRun) {
            $this->warn('dry-run فعال است؛ تغییری ذخیره نشد.');
        } else {
            $this->info('ادغام طرف حساب پیمانکار با پرسنل انجام شد.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{invoices: int, accounting_lines: int, treasury: int}
     */
    private function previewMerge(Employee $employee, Party $contractorParty): array
    {
        $this->info("--- پیش‌نمایش ادغام contractor#{$contractorParty->id} → party#{$employee->party_id} ---");

        return [
            'invoices' => Invoice::where('party_id', $contractorParty->id)->count(),
            'accounting_lines' => AccountingDocumentLine::where('party_id', $contractorParty->id)->count(),
            'treasury' => TreasuryTransaction::withTrashed()->where('party_id', $contractorParty->id)->count(),
        ];
    }

    /**
     * @return array{invoices: int, accounting_lines: int, treasury: int}
     */
    private function executeMerge(Employee $employee, Party $contractorParty): array
    {
        return DB::transaction(function () use ($employee, $contractorParty): array {
            $employeeParty = Party::with('types')->findOrFail($employee->party_id);

            $invoiceCount = Invoice::where('party_id', $contractorParty->id)
                ->update(['party_id' => $employeeParty->id]);

            $lineCount = AccountingDocumentLine::where('party_id', $contractorParty->id)
                ->update(['party_id' => $employeeParty->id]);

            $treasuryCount = TreasuryTransaction::withTrashed()
                ->where('party_id', $contractorParty->id)
                ->update(['party_id' => $employeeParty->id]);

            $paymentCount = PaymentVoucher::where('party_id', $contractorParty->id)
                ->update(['party_id' => $employeeParty->id]);

            $receiptCount = ReceiptVoucher::where('party_id', $contractorParty->id)
                ->update(['party_id' => $employeeParty->id]);

            $vendorType = PartyType::where('name', 'vendor')->first();
            $contractorType = PartyType::where('name', 'contractor')->first();
            $colleagueType = PartyType::where('name', 'colleague')->first();

            $typeIds = collect([
                $vendorType?->id,
                $contractorType?->id,
                $colleagueType?->id,
                ...$employeeParty->types->pluck('id')->all(),
            ])->filter()->unique()->values()->all();

            $employeeParty->types()->sync($typeIds);
            $employeeParty->update([
                'notes' => trim(preg_replace('/\s*\|\s*طرف حساب حقوق و دستمزد پرسنل/u', '', (string) $employeeParty->notes) ?: '') ?: null,
            ]);

            $contractorParty->delete();

            $this->line("  payment vouchers moved: {$paymentCount} | receipt vouchers: {$receiptCount}");

            return [
                'invoices' => $invoiceCount,
                'accounting_lines' => $lineCount,
                'treasury' => $treasuryCount,
            ];
        });
    }
}
