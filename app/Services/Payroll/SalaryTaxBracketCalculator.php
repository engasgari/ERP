<?php

namespace App\Services\Payroll;

/**
 * Progressive monthly salary tax (Iran).
 * Brackets must come from a versioned legal parameter set — never hardcode a year blindly.
 */
class SalaryTaxBracketCalculator
{
    /**
     * @param  list<array{up_to: int|float|null, rate: int|float}>  $brackets
     * @return array{
     *   tax_amount: float,
     *   taxable_income: float,
     *   exemption_amount: float,
     *   brackets_applied: list<array{from: float, to: float|null, rate: float, taxable_slice: float, tax: float}>
     * }
     */
    public function calculateMonthly(float $taxableIncome, array $brackets): array
    {
        $income = max(0, round($taxableIncome, 2));
        $remaining = $income;
        $previousCap = 0.0;
        $tax = 0.0;
        $exemption = 0.0;
        $applied = [];

        foreach ($brackets as $bracket) {
            $rate = (float) ($bracket['rate'] ?? 0);
            $upTo = array_key_exists('up_to', $bracket) ? $bracket['up_to'] : null;
            $cap = $upTo === null ? null : (float) $upTo;

            if ($cap === null) {
                $slice = $remaining;
            } else {
                $bandWidth = max(0, $cap - $previousCap);
                $slice = min($remaining, $bandWidth);
            }

            $slice = round($slice, 2);
            $sliceTax = round($slice * ($rate / 100), 2);

            if ($rate <= 0) {
                $exemption += $slice;
            } else {
                $tax += $sliceTax;
            }

            $applied[] = [
                'from' => $previousCap,
                'to' => $cap,
                'rate' => $rate,
                'taxable_slice' => $slice,
                'tax' => $sliceTax,
            ];

            $remaining = round($remaining - $slice, 2);
            $previousCap = $cap ?? $previousCap;

            if ($remaining <= 0) {
                break;
            }
        }

        return [
            'tax_amount' => round($tax, 2),
            'taxable_income' => $income,
            'exemption_amount' => round($exemption, 2),
            'brackets_applied' => $applied,
        ];
    }
}
