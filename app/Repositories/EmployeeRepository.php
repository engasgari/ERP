<?php

namespace App\Repositories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EmployeeRepository
{
    /**
     * Active employed staff for payroll/attendance workflows.
     *
     * @return Collection<int, Employee>
     */
    public function activeForPayroll(): Collection
    {
        return $this->activeQuery()
            ->with(['party', 'employmentOrders' => fn ($q) => $q->where('status', 'approved')->orderByDesc('id')])
            ->orderBy('id')
            ->get();
    }

    public function activeQuery(): Builder
    {
        return Employee::query()
            ->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->whereNull('status')
                    ->orWhereNotIn('status', ['inactive', 'terminated', 'archived']);
            })
            ->whereNull('archived_at')
            ->where(function (Builder $query): void {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', now()->toDateString());
            });
    }
}
