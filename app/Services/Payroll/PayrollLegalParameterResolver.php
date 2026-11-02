<?php

namespace App\Services\Payroll;

use App\Models\PayrollLegalParameterSet;
use App\Models\PayrollPeriod;
use Carbon\Carbon;

/**
 * Resolves versioned legal payroll parameters for a Jalali year / period date.
 * Never invents rates for years without an explicit parameter set.
 */
class PayrollLegalParameterResolver
{
    public function forPeriod(PayrollPeriod $period): ?PayrollLegalParameterSet
    {
        $asOf = $period->starts_at
            ? Carbon::parse($period->starts_at)->toDateString()
            : null;

        return $this->forJalaliYear((int) $period->year, $asOf);
    }

    public function forJalaliYear(int $jalaliYear, ?string $asOfDate = null): ?PayrollLegalParameterSet
    {
        $query = PayrollLegalParameterSet::query()
            ->where('jalali_year', $jalaliYear)
            ->where('status', 'active')
            ->orderByDesc('effective_from');

        if ($asOfDate) {
            $query->whereDate('effective_from', '<=', $asOfDate)
                ->where(function ($q) use ($asOfDate): void {
                    $q->whereNull('effective_to')
                        ->orWhereDate('effective_to', '>=', $asOfDate);
                });
        }

        return $query->first();
    }

    /**
     * Labor-law coefficients that are statutory (not annual budget figures).
     *
     * @return array{overtime_multiplier: float, night_premium_rate: float, holiday_work_multiplier: float, friday_work_multiplier: float}
     */
    public function statutoryPremiumDefaults(): array
    {
        return [
            // Labor Law Art.59 — overtime = regular hourly × 1.4 (+40%)
            'overtime_multiplier' => 1.4,
            // Labor Law Art.53
            'night_premium_rate' => 0.35,
            // Company/common Iranian payroll practice for holiday & weekly-rest work
            // paid as a separate line on top of monthly salary: hourly × 2.4
            // (= 1.0 work wage for those hours + 1.4 Art.59-style premium).
            // Legal minimum for the premium portion is +40%; coefficient is versioned
            // in payroll_legal_parameter_sets and must not be invented per year blindly.
            'holiday_work_multiplier' => 2.4,
            'friday_work_multiplier' => 2.4,
        ];
    }

    public function insuranceCeilingMonthly(?PayrollLegalParameterSet $set, int $daysInMonth = 30): ?float
    {
        if (! $set) {
            return null;
        }

        $minDaily = (float) $set->parameter('min_daily_wage', 0);
        $multiplier = (float) $set->parameter('insurance_ceiling_daily_multiplier', 7);

        if ($minDaily <= 0 || $multiplier <= 0) {
            return null;
        }

        return round($minDaily * $multiplier * max(1, $daysInMonth), 2);
    }
}
