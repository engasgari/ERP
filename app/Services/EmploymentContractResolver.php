<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmploymentOrder;

class EmploymentContractResolver
{
    /**
     * Resolve the operational contract type used by attendance/payroll.
     *
     * Returns one of: monthly, hourly, project.
     */
    public function resolve(Employee $employee, ?EmploymentOrder $order = null): string
    {
        $employmentType = strtolower((string) ($order?->employment_type ?: $employee->employment_type ?: ''));
        $salaryType = strtolower((string) ($employee->salary_type ?: ''));

        if ($this->isHourly($employmentType, $salaryType)) {
            return 'hourly';
        }

        if ($this->isProjectBased($employmentType, $salaryType)) {
            return 'project';
        }

        return 'monthly';
    }

    public function isWorkBased(Employee $employee, ?EmploymentOrder $order = null): bool
    {
        return in_array($this->resolve($employee, $order), ['hourly', 'project'], true);
    }

    private function isHourly(string $employmentType, string $salaryType): bool
    {
        return in_array($employmentType, ['hourly', 'hourly_contract'], true)
            || $salaryType === 'hourly';
    }

    private function isProjectBased(string $employmentType, string $salaryType): bool
    {
        return in_array($employmentType, ['project', 'project_contract', 'project_based', 'project_based_contract'], true)
            || $salaryType === 'project';
    }
}
