<?php

namespace App\Services;

use App\Models\EmploymentOrder;

class PersonnelDecreeService
{
    public function __construct(private readonly EmploymentOrderService $orders)
    {
    }

    public function approve(EmploymentOrder $decree, int $userId): EmploymentOrder
    {
        return $this->orders->approve($decree, $userId);
    }

    public function revert(EmploymentOrder $decree, int $userId): EmploymentOrder
    {
        return $this->orders->revert($decree, $userId);
    }

    public function activeForPayroll(int $employeeId, mixed $period): ?EmploymentOrder
    {
        return EmploymentOrder::query()
            ->where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->whereDate('effective_date', '<=', $period->ends_at)
            ->where(function ($query) use ($period): void {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $period->starts_at);
            })
            ->latest('effective_date')
            ->first();
    }
}
