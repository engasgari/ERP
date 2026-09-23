<?php

namespace App\Support\Hr;

use App\Models\Employee;
use App\Models\EmploymentOrder;

class InsuranceExemptionResolver
{
    /**
     * مدیرعامل و مدیران مشمول بیمه بیکاری (۳٪) نیستند.
     */
    public static function isExemptFromUnemployment(Employee $employee, ?EmploymentOrder $order = null): bool
    {
        if (! $employee->relationLoaded('positionRecord')) {
            $employee->loadMissing('positionRecord');
        }

        if ($order && ! $order->relationLoaded('position')) {
            $order->loadMissing('position');
        }

        foreach (self::candidateLabels($employee, $order) as $label) {
            if (self::matchesManagingDirector($label)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function candidateLabels(Employee $employee, ?EmploymentOrder $order): array
    {
        return array_values(array_filter([
            (string) $employee->position,
            (string) ($employee->positionRecord?->title),
            (string) ($employee->positionRecord?->code),
            (string) ($order?->position?->title),
            (string) ($order?->position?->code),
        ], fn (string $value): bool => trim($value) !== ''));
    }

    private static function matchesManagingDirector(string $value): bool
    {
        $normalized = mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value));

        if (in_array($normalized, ['ceo', 'managing_director', 'managing director'], true)) {
            return true;
        }

        return str_contains($normalized, 'مدیرعامل')
            || str_contains($normalized, 'مدیر عامل');
    }
}
