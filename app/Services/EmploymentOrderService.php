<?php

namespace App\Services;

use App\Core\Base\BaseService;
use App\Events\EmployeePositionChanged;
use App\Events\EmployeeTerminated;
use App\Events\EmploymentOrderApproved;
use App\Models\EmploymentContract;
use App\Models\EmploymentOrder;
use App\Repositories\EmploymentOrderRepository;
use Illuminate\Validation\ValidationException;

class EmploymentOrderService extends BaseService
{
    public function __construct(
        private readonly EmployeeHistoryService $history,
        private readonly ContractGenerationService $contracts,
        private readonly FiscalPeriodService $periods,
        private readonly EmploymentOrderRepository $orders,
        private readonly EmploymentOrderCalculationService $calculations,
    ) {
    }

    public function saveDraft(array $header, array $lines, ?int $orderId, int $userId): EmploymentOrder
    {
        return $this->transaction(function () use ($header, $lines, $orderId, $userId) {
            $normalizedLines = $this->normalizePersistableLines($lines);
            if ($normalizedLines === []) {
                throw ValidationException::withMessages([
                    'lines' => 'حداقل یک قلم در اقلام حکم الزامی است.',
                ]);
            }

            $legacy = $this->calculations->legacyColumnMap($normalizedLines);
            $payload = array_merge($header, $legacy, [
                'status' => 'draft',
                'created_by' => $userId,
            ]);

            if ($orderId) {
                $order = $this->orders->findWithDetails($orderId);
                if ($order->status === 'approved') {
                    throw ValidationException::withMessages([
                        'status' => 'حکم تایید شده قابل ویرایش نیست.',
                    ]);
                }
                $this->orders->update($order, $payload);
            } else {
                $order = $this->orders->create($payload);
            }

            $this->orders->syncLines($order, $normalizedLines);

            return $this->orders->findWithDetails($order->id);
        });
    }

    public function approve(EmploymentOrder $order, int $userId): EmploymentOrder
    {
        return $this->transaction(function () use ($order, $userId) {
            $order->loadMissing(['employee', 'lines']);
            $this->periods->ensureDateIsAllowed($order->effective_date);
            $employee = $order->employee;

            $baseSalary = (float) ($order->lines->firstWhere('code', 'base_salary')?->amount ?? $order->base_salary);

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
                'base_salary' => $baseSalary,
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
        return $this->transaction(function () use ($order, $userId) {
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

    public function deleteDraft(EmploymentOrder $order): void
    {
        if ($order->status === 'approved') {
            throw ValidationException::withMessages([
                'status' => 'حکم تایید شده قابل حذف نیست.',
            ]);
        }

        $order->lines()->delete();
        $order->delete();
    }

    private function normalizePersistableLines(array $lines): array
    {
        return collect($lines)
            ->map(function (array $line, int $index): array {
                $amount = $this->normalizeMoney($line['amount'] ?? 0);

                return [
                    'salary_item_id' => $line['salary_item_id'] ?? null,
                    'code' => $line['code'] ?? null,
                    'title' => (string) ($line['title'] ?? ''),
                    'type' => (string) ($line['type'] ?? 'earning'),
                    'amount' => $amount,
                    'is_insurable' => (bool) ($line['is_insurable'] ?? false),
                    'is_taxable' => (bool) ($line['is_taxable'] ?? false),
                    'is_editable' => (bool) ($line['is_editable'] ?? true),
                    'is_removable' => (bool) ($line['is_removable'] ?? true),
                    'sort_order' => (int) ($line['sort_order'] ?? ($index + 1)),
                ];
            })
            ->filter(fn (array $line) => $line['title'] !== '' && ($line['amount'] > 0 || $line['code'] === 'base_salary'))
            ->values()
            ->all();
    }

    private function normalizeMoney(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalized = normalizePersianDigits((string) $value);
        $normalized = str_replace([',', '٬', ' ', '‌'], '', (string) $normalized);

        return is_numeric($normalized) ? (float) $normalized : 0.0;
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
