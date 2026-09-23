<?php

namespace App\Services;

use App\Models\InsuranceLiability;
use App\Models\InsurancePeriod;
use App\Models\InsuranceRecord;
use App\Models\PayrollPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InsuranceLiabilityService
{
    public function syncFromPayrollPeriod(PayrollPeriod $payrollPeriod): InsurancePeriod
    {
        return DB::transaction(function () use ($payrollPeriod) {
            $insurancePeriod = InsurancePeriod::query()->updateOrCreate(
                ['payroll_period_id' => $payrollPeriod->id],
                [
                    'year' => $payrollPeriod->year,
                    'month' => $payrollPeriod->month,
                    'title' => $payrollPeriod->persian_title,
                    'starts_at' => $payrollPeriod->starts_at,
                    'ends_at' => $payrollPeriod->ends_at,
                    'status' => 'open',
                ]
            );

            $totals = InsuranceRecord::query()
                ->where('payroll_period_id', $payrollPeriod->id)
                ->selectRaw('COALESCE(SUM(employee_share), 0) as employee_share')
                ->selectRaw('COALESCE(SUM(employer_share), 0) as employer_share')
                ->selectRaw('COALESCE(SUM(unemployment_share), 0) as unemployment_share')
                ->first();

            $employeeShare = round((float) ($totals->employee_share ?? 0), 2);
            $employerShare = round((float) ($totals->employer_share ?? 0), 2);
            $unemploymentShare = round((float) ($totals->unemployment_share ?? 0), 2);
            $principal = round($employeeShare + $employerShare + $unemploymentShare, 2);

            $liability = InsuranceLiability::query()->firstOrNew([
                'insurance_period_id' => $insurancePeriod->id,
            ]);

            $penalty = round((float) ($liability->penalty_amount ?? 0), 2);
            $other = round((float) ($liability->other_amount ?? 0), 2);
            $paid = round((float) ($liability->paid_amount ?? 0), 2);

            $liability->fill([
                'employee_share' => $employeeShare,
                'employer_share' => $employerShare,
                'unemployment_share' => $unemploymentShare,
                'principal_amount' => $principal,
                'penalty_amount' => $penalty,
                'other_amount' => $other,
                'paid_amount' => $paid,
                'synced_at' => now(),
            ]);

            $this->recalculateLiability($liability);
            $liability->save();

            return $insurancePeriod->fresh(['liability']);
        });
    }

    public function syncAll(?int $year = null): int
    {
        $query = PayrollPeriod::query()->orderBy('year')->orderBy('month');

        if ($year) {
            $query->where('year', $year);
        }

        $count = 0;

        foreach ($query->get() as $period) {
            if (InsuranceRecord::where('payroll_period_id', $period->id)->exists()) {
                $this->syncFromPayrollPeriod($period);
                $count++;
            }
        }

        return $count;
    }

    public function updateManualAmounts(InsuranceLiability $liability, array $data): InsuranceLiability
    {
        if ((float) $liability->paid_amount > 0.009) {
            throw new \RuntimeException('برای دوره‌ای که پرداخت ثبت شده، امکان تغییر جریمه/سایر مبالغ وجود ندارد.');
        }

        $liability->penalty_amount = round(max(0, (float) ($data['penalty_amount'] ?? $liability->penalty_amount)), 2);
        $liability->other_amount = round(max(0, (float) ($data['other_amount'] ?? $liability->other_amount)), 2);
        $this->recalculateLiability($liability);
        $liability->save();

        return $liability->refresh();
    }

    public function recalculateLiability(InsuranceLiability $liability): void
    {
        $totalDue = round(
            (float) $liability->principal_amount
            + (float) $liability->penalty_amount
            + (float) $liability->other_amount,
            2
        );
        $paid = round((float) $liability->paid_amount, 2);
        $balance = max(0, round($totalDue - $paid, 2));

        $liability->balance_amount = $balance;
        $liability->status = $this->resolveStatus($totalDue, $paid, $balance);
    }

    public function applyPayment(InsuranceLiability $liability, float $lineTotal): void
    {
        $liability->paid_amount = round((float) $liability->paid_amount + $lineTotal, 2);
        $this->recalculateLiability($liability);
        $liability->save();
    }

    public function reversePayment(InsuranceLiability $liability, float $lineTotal): void
    {
        $liability->paid_amount = max(0, round((float) $liability->paid_amount - $lineTotal, 2));
        $this->recalculateLiability($liability);
        $liability->save();
    }

    public function summary(?int $year = null, ?string $status = null): array
    {
        $query = InsuranceLiability::query()->with('period');

        if ($year) {
            $query->whereHas('period', fn ($period) => $period->where('year', $year));
        }

        if ($status) {
            $query->where('status', $status);
        }

        $rows = $query->get();

        return [
            'principal' => round((float) $rows->sum('principal_amount'), 2),
            'penalty' => round((float) $rows->sum('penalty_amount'), 2),
            'other' => round((float) $rows->sum('other_amount'), 2),
            'paid' => round((float) $rows->sum('paid_amount'), 2),
            'balance' => round((float) $rows->sum('balance_amount'), 2),
        ];
    }

    /**
     * @return Collection<int, InsuranceLiability>
     */
    public function payableLiabilities(array $periodIds = []): Collection
    {
        $query = InsuranceLiability::query()
            ->with('period')
            ->where('balance_amount', '>', 0.009)
            ->orderBy(
                InsurancePeriod::select('year')
                    ->whereColumn('insurance_periods.id', 'insurance_liabilities.insurance_period_id')
            )
            ->orderBy(
                InsurancePeriod::select('month')
                    ->whereColumn('insurance_periods.id', 'insurance_liabilities.insurance_period_id')
            );

        if ($periodIds !== []) {
            $query->whereIn('insurance_period_id', $periodIds);
        }

        return $query->get();
    }

    private function resolveStatus(float $totalDue, float $paid, float $balance): string
    {
        if ($totalDue <= 0.009) {
            return 'settled';
        }

        if ($balance <= 0.009) {
            return 'settled';
        }

        if ($paid <= 0.009) {
            return 'unpaid';
        }

        return 'partial';
    }
}
