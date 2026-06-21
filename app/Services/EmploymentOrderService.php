<?php

namespace App\Services;

use App\Events\EmployeePositionChanged;
use App\Events\EmployeeTerminated;
use App\Events\EmploymentOrderApproved;
use App\Models\EmploymentContract;
use App\Models\EmploymentOrder;
use Illuminate\Support\Facades\DB;

class EmploymentOrderService
{
    public function __construct(
        private readonly EmployeeHistoryService $history,
        private readonly ContractGenerationService $contracts,
        private readonly FiscalPeriodService $periods,
    ) {
    }

    public function approve(EmploymentOrder $order, int $userId): EmploymentOrder
    {
        return DB::transaction(function () use ($order, $userId) {
            $order->loadMissing('employee');
            $this->periods->ensureDateIsAllowed($order->effective_date);
            $employee = $order->employee;

            $oldValues = $employee->only([
                'position_id',
                'organization_unit_id',
                'default_cost_center',
                'default_project_id',
                'employment_type',
                'salary_type',
                'base_salary',
                'hourly_rate',
                'insurance_number',
                'hire_date',
                'is_active',
                'status',
                'termination_date',
            ]);

            $employee->fill([
                'position_id' => $order->position_id,
                'organization_unit_id' => $order->organization_unit_id,
                'default_cost_center' => $order->cost_center_code,
                'default_project_id' => $order->default_project_id,
                'employment_type' => $order->employment_type,
                'salary_type' => $this->salaryTypeFor($order->employment_type),
                'base_salary' => $order->base_salary,
                'hourly_rate' => $order->hourly_rate,
                'status' => $order->order_type === 'termination' ? 'terminated' : 'active',
                'is_active' => $order->order_type !== 'termination',
                'hire_date' => $employee->hire_date ?: $order->effective_date,
                'termination_date' => $order->order_type === 'termination' ? $order->effective_date : $employee->termination_date,
            ])->save();

            $order->update([
                'status' => 'approved',
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            $this->history->record(
                $employee,
                $order->order_type,
                'تایید حکم کارگزینی ' . $order->number,
                $oldValues,
                $employee->only(array_keys($oldValues)) + ['effective_date' => $order->effective_date?->toDateString()],
                $order,
                $userId
            );

            $this->contracts->fromEmploymentOrder($order->refresh(), $userId);

            event(new EmploymentOrderApproved($order));
            event(new EmployeePositionChanged($employee, $order));

            if ($order->order_type === 'termination') {
                event(new EmployeeTerminated($employee, $order));
            }

            return $order;
        });
    }

    public function revert(EmploymentOrder $order, int $userId): EmploymentOrder
    {
        return DB::transaction(function () use ($order, $userId) {
            $order->loadMissing('employee');
            $this->periods->ensureDateIsAllowed($order->effective_date);
            $employee = $order->employee;

            $history = $employee->history()
                ->where('source_type', EmploymentOrder::class)
                ->where('source_id', $order->id)
                ->latest('id')
                ->first();

            $oldValues = $history?->old_values ?? [];
            $currentValues = $employee->only([
                'position_id',
                'organization_unit_id',
                'default_cost_center',
                'default_project_id',
                'employment_type',
                'salary_type',
                'base_salary',
                'hourly_rate',
                'insurance_number',
                'hire_date',
                'is_active',
                'status',
                'termination_date',
            ]);

            $restoreValues = [
                'position_id' => $oldValues['position_id'] ?? null,
                'organization_unit_id' => $oldValues['organization_unit_id'] ?? null,
                'default_cost_center' => $oldValues['default_cost_center'] ?? null,
                'default_project_id' => $oldValues['default_project_id'] ?? null,
                'employment_type' => $oldValues['employment_type'] ?? $employee->employment_type,
                'salary_type' => $oldValues['salary_type'] ?? $employee->salary_type,
                'base_salary' => $oldValues['base_salary'] ?? $employee->base_salary,
                'hourly_rate' => $oldValues['hourly_rate'] ?? $employee->hourly_rate,
                'insurance_number' => $oldValues['insurance_number'] ?? $employee->insurance_number,
                'hire_date' => $oldValues['hire_date'] ?? $employee->hire_date,
                'is_active' => $oldValues['is_active'] ?? true,
                'status' => $oldValues['status'] ?? 'active',
                'termination_date' => $oldValues['termination_date'] ?? null,
            ];

            $employee->fill($restoreValues)->save();

            EmploymentContract::where('employment_order_id', $order->id)->delete();

            $order->update([
                'status' => 'draft',
                'approved_by' => null,
                'approved_at' => null,
            ]);

            $this->history->record(
                $employee,
                'approval_reverted',
                'برگشت از ثبت قطعی حکم ' . $order->number,
                $currentValues,
                $restoreValues + ['effective_date' => $order->effective_date?->toDateString()],
                $order,
                $userId
            );

            return $order->refresh();
        });
    }

    private function salaryTypeFor(?string $employmentType): string
    {
        return match ($employmentType) {
            'hourly', 'hourly_contract' => 'hourly',
            'project', 'project_contract', 'project_based', 'project_based_contract' => 'project',
            default => 'monthly',
        };
    }
}
