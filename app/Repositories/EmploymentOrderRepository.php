<?php

namespace App\Repositories;

use App\Core\Base\BaseRepository;
use App\Models\EmploymentOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EmploymentOrderRepository extends BaseRepository
{
    public function __construct()
    {
        $this->model = new EmploymentOrder();
    }

    public function paginate(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        return $this->filter($this->query(), $filters)
            ->with(['employee.party', 'position', 'organizationUnit', 'job', 'lines.salaryItem'])
            ->latest()
            ->paginate($perPage);
    }

    public function findWithDetails(int $id): EmploymentOrder
    {
        return $this->query()
            ->with(['employee.party', 'position', 'organizationUnit', 'job', 'lines.salaryItem', 'project'])
            ->findOrFail($id);
    }

    public function historyForEmployee(int $employeeId, ?int $excludeOrderId = null): Collection
    {
        return $this->query()
            ->with(['position', 'lines'])
            ->where('employee_id', $employeeId)
            ->when($excludeOrderId, fn (Builder $query) => $query->where('id', '!=', $excludeOrderId))
            ->latest('effective_date')
            ->latest('id')
            ->limit(50)
            ->get();
    }

    public function syncLines(EmploymentOrder $order, array $lines): void
    {
        $order->lines()->delete();

        foreach (array_values($lines) as $index => $line) {
            $order->lines()->create([
                'salary_item_id' => $line['salary_item_id'] ?? null,
                'code' => $line['code'] ?? null,
                'title' => $line['title'],
                'type' => $line['type'] ?? 'earning',
                'amount' => (float) ($line['amount'] ?? 0),
                'is_insurable' => (bool) ($line['is_insurable'] ?? false),
                'is_taxable' => (bool) ($line['is_taxable'] ?? false),
                'is_editable' => (bool) ($line['is_editable'] ?? true),
                'is_removable' => (bool) ($line['is_removable'] ?? true),
                'sort_order' => (int) ($line['sort_order'] ?? ($index + 1)),
            ]);
        }
    }

    private function filter(Builder $query, array $filters): Builder
    {
        if (($filters['search'] ?? '') !== '') {
            $search = trim((string) $filters['search']);
            $query->where(fn (Builder $builder) => $builder
                ->where('number', 'like', "%{$search}%")
                ->orWhereHas('employee.party', fn (Builder $party) => $party->where('name', 'like', "%{$search}%")));
        }

        if (($filters['status'] ?? '') !== '') {
            $query->where('status', $filters['status']);
        }

        return $query;
    }
}
