<?php

namespace App\Services;

use App\Models\AccountingDocument;
use App\Models\InsuranceRecord;
use App\Models\MonthlyAttendance;
use App\Models\PayrollAccountingEntry;
use App\Models\PayrollAudit;
use App\Models\PayrollCalculation;
use App\Models\PayrollCalculationLine;
use App\Models\PayrollPayment;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\TaxRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PayrollCalculationService
{
    public function createOrGetPeriod(int $year, int $month, ?int $userId = null): PayrollPeriod
    {
        $range = jalaliMonthRangeGregorianSafe($year, $month);

        return PayrollPeriod::firstOrCreate(
            ['year' => $year, 'month' => $month],
            [
                'title' => getPersianMonthName($month) . ' ' . $year,
                'starts_at' => $range['start']->toDateString(),
                'ends_at' => $range['end']->toDateString(),
                'status' => 'draft',
                'created_by' => $userId,
            ]
        );
    }

    public function calculate(PayrollPeriod $period, ?int $userId = null): Collection
    {
        return app(NewPayrollEngineService::class)->runFullLifecycle($period);
    }

    public function approve(PayrollPeriod $period, int $userId): void
    {
        DB::transaction(function () use ($period, $userId) {
            $period->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $userId,
            ]);

            PayrollCalculation::query()
                ->where('payroll_period_id', $period->id)
                ->where('status', 'calculated')
                ->update([
                    'approved_at' => now(),
                    'approved_by' => $userId,
                ]);
        });
    }

    public function deletePeriod(PayrollPeriod $period): void
    {
        DB::transaction(function () use ($period): void {
            $calculationIds = PayrollCalculation::where('payroll_period_id', $period->id)->pluck('id');

            if ($calculationIds->isNotEmpty()) {
                $paymentIds = PayrollPayment::whereIn('payroll_calculation_id', $calculationIds)->pluck('id');

                PayrollCalculationLine::whereIn('payroll_calculation_id', $calculationIds)->delete();
                PayrollAccountingEntry::whereIn('payroll_calculation_id', $calculationIds)->delete();
                InsuranceRecord::whereIn('payroll_calculation_id', $calculationIds)->delete();
                TaxRecord::whereIn('payroll_calculation_id', $calculationIds)->delete();
                Payslip::whereIn('payroll_calculation_id', $calculationIds)->delete();
                PayrollPayment::whereIn('payroll_calculation_id', $calculationIds)->delete();

                AccountingDocument::query()
                    ->where('source_type', PayrollCalculation::class)
                    ->whereIn('source_id', $calculationIds)
                    ->get()
                    ->each(function (AccountingDocument $document): void {
                        $document->lines()->delete();
                        $document->forceDelete();
                    });

                if ($paymentIds->isNotEmpty()) {
                    AccountingDocument::query()
                        ->where('source_type', PayrollPayment::class)
                        ->whereIn('source_id', $paymentIds)
                        ->get()
                        ->each(function (AccountingDocument $document): void {
                            $document->lines()->delete();
                            $document->forceDelete();
                        });
                }

                PayrollAudit::where('auditable_type', PayrollCalculation::class)
                    ->whereIn('auditable_id', $calculationIds)
                    ->delete();

                MonthlyAttendance::where('payroll_period_id', $period->id)->delete();
                PayrollCalculation::where('payroll_period_id', $period->id)->delete();
            }

            $period->delete();
        });
    }

    /**
     * @param  array<int>|Collection<int, int>  $calculationIds
     */
    public function deleteCalculations(array|Collection $calculationIds): void
    {
        $calculationIds = collect($calculationIds)->filter()->unique()->values();

        if ($calculationIds->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($calculationIds): void {
            $paymentIds = PayrollPayment::whereIn('payroll_calculation_id', $calculationIds)->pluck('id');

            PayrollCalculationLine::whereIn('payroll_calculation_id', $calculationIds)->delete();
            PayrollAccountingEntry::whereIn('payroll_calculation_id', $calculationIds)->delete();
            InsuranceRecord::whereIn('payroll_calculation_id', $calculationIds)->delete();
            TaxRecord::whereIn('payroll_calculation_id', $calculationIds)->delete();
            Payslip::whereIn('payroll_calculation_id', $calculationIds)->delete();
            PayrollPayment::whereIn('payroll_calculation_id', $calculationIds)->delete();

            AccountingDocument::query()
                ->where('source_type', PayrollCalculation::class)
                ->whereIn('source_id', $calculationIds)
                ->get()
                ->each(function (AccountingDocument $document): void {
                    $document->lines()->delete();
                    $document->forceDelete();
                });

            if ($paymentIds->isNotEmpty()) {
                AccountingDocument::query()
                    ->where('source_type', PayrollPayment::class)
                    ->whereIn('source_id', $paymentIds)
                    ->get()
                    ->each(function (AccountingDocument $document): void {
                        $document->lines()->delete();
                        $document->forceDelete();
                    });
            }

            PayrollAudit::query()
                ->where('auditable_type', PayrollCalculation::class)
                ->whereIn('auditable_id', $calculationIds)
                ->delete();

            PayrollCalculation::whereIn('id', $calculationIds)->delete();
        });
    }

    public function reopen(PayrollPeriod $period): void
    {
        if ($period->status !== 'closed') {
            throw new \RuntimeException('فقط دوره بسته‌شده قابل بازگشایی است.');
        }

        $period->update([
            'status' => 'approved',
            'closed_at' => null,
        ]);
    }

    public function reopenClosedPeriodsForYear(int $year): int
    {
        return PayrollPeriod::query()
            ->where('year', $year)
            ->where('status', 'closed')
            ->update([
                'status' => 'approved',
                'closed_at' => null,
            ]);
    }
}
