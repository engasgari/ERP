<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmploymentOrder;
use App\Models\PayrollPeriod;
use App\Models\Payslip;

class PayslipSnapshotService
{
    /**
     * Resolve personnel header fields for payslip from the covering employment order,
     * falling back to the employee master data.
     *
     * @return array{
     *   full_name: string,
     *   personnel_code: string,
     *   national_code: string,
     *   organization_unit: string,
     *   position: string,
     *   organization_unit_id: ?int,
     *   position_id: ?int,
     *   employment_order_id: ?int,
     *   employment_order_number: ?string,
     *   bank_account_number: ?string,
     *   insurance_number: ?string,
     *   source: string
     * }
     */
    public function resolvePersonnel(Employee $employee, ?EmploymentOrder $order = null): array
    {
        $order?->loadMissing(['organizationUnit', 'position', 'job']);
        $employee->loadMissing(['organizationUnit', 'positionRecord']);

        $organizationUnitId = $order?->organization_unit_id ?: $employee->organization_unit_id;
        $positionId = $order?->position_id ?: $employee->position_id;

        $organizationUnit = $order?->organizationUnit?->title
            ?: $employee->organizationUnit?->title
            ?: (filled($employee->department) ? (string) $employee->department : null)
            ?: '-';

        $position = $order?->position?->title
            ?: $order?->job?->title
            ?: $employee->positionRecord?->title
            ?: (filled($employee->position) ? (string) $employee->position : null)
            ?: '-';

        $source = $order?->organization_unit_id || $order?->position_id || $order?->job_id
            ? 'employment_order'
            : 'employee';

        return [
            'full_name' => (string) $employee->full_name,
            'personnel_code' => (string) ($employee->personnel_code ?: $employee->employee_code ?: '-'),
            'national_code' => (string) ($employee->national_code ?: '-'),
            'organization_unit' => $organizationUnit,
            'position' => $position,
            'organization_unit_id' => $organizationUnitId ? (int) $organizationUnitId : null,
            'position_id' => $positionId ? (int) $positionId : null,
            'employment_order_id' => $order?->id,
            'employment_order_number' => $order?->number,
            'bank_account_number' => $employee->bank_account_number,
            'insurance_number' => $employee->insurance_number,
            'source' => $source,
        ];
    }

    /**
     * @param  array<string, mixed>  $totals
     * @return array<string, mixed>
     */
    public function build(
        Employee $employee,
        PayrollPeriod $period,
        ?EmploymentOrder $order,
        array $totals,
    ): array {
        $personnel = $this->resolvePersonnel($employee, $order);

        return [
            'personnel' => $personnel,
            'employee' => $personnel['full_name'],
            'period' => $period->persian_title,
            'year' => $period->year,
            'month' => $period->month,
            'net_payable' => $totals['net_payable'] ?? 0,
            'gross_salary' => $totals['gross_salary'] ?? null,
            'total_deductions' => $totals['total_deductions'] ?? null,
            'insurance_base' => $totals['insurance_base'] ?? null,
            'tax_base' => $totals['tax_base'] ?? null,
            'non_insurance_earnings' => $totals['non_insurance_earnings'] ?? null,
            'non_tax_earnings' => $totals['non_tax_earnings'] ?? null,
            'insurance_employee' => $totals['insurance_employee'] ?? null,
            'insurance_employer' => $totals['insurance_employer'] ?? null,
        ];
    }

    /**
     * Prefer immutable snapshot on the payslip; refresh missing org/position from current order/employee.
     *
     * @return array<string, mixed>
     */
    public function headerForPrint(\App\Models\Payslip $payslip): array
    {
        $payslip->loadMissing([
            'employee.organizationUnit',
            'employee.positionRecord',
            'calculation.period',
            'period',
        ]);

        $snapshot = is_array($payslip->snapshot) ? $payslip->snapshot : [];
        $personnel = is_array($snapshot['personnel'] ?? null) ? $snapshot['personnel'] : [];

        $order = null;
        $calculation = $payslip->calculation;
        if ($calculation?->personnel_decree_id) {
            $order = EmploymentOrder::query()
                ->with(['organizationUnit', 'position', 'job'])
                ->find($calculation->personnel_decree_id);
        }

        $resolved = $this->resolvePersonnel($payslip->employee, $order);

        $organizationUnit = filled($personnel['organization_unit'] ?? null) && ($personnel['organization_unit'] !== '-')
            ? (string) $personnel['organization_unit']
            : $resolved['organization_unit'];

        $position = filled($personnel['position'] ?? null) && ($personnel['position'] !== '-')
            ? (string) $personnel['position']
            : $resolved['position'];

        return [
            'full_name' => (string) ($personnel['full_name'] ?? $snapshot['employee'] ?? $resolved['full_name']),
            'personnel_code' => (string) ($personnel['personnel_code'] ?? $resolved['personnel_code']),
            'national_code' => (string) ($personnel['national_code'] ?? $resolved['national_code']),
            'organization_unit' => $organizationUnit,
            'position' => $position,
            'employment_order_number' => $personnel['employment_order_number'] ?? $resolved['employment_order_number'],
            'bank_account_number' => $personnel['bank_account_number'] ?? $resolved['bank_account_number'],
            'insurance_number' => $personnel['insurance_number'] ?? $resolved['insurance_number'],
            'period_title' => (string) ($snapshot['period'] ?? $payslip->period?->persian_title ?? $calculation?->period?->persian_title ?? '-'),
        ];
    }
}
