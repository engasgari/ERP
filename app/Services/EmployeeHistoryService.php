<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;

class EmployeeHistoryService
{
    public function record(Employee $employee, string $event, string $title, array $oldValues = [], array $newValues = [], ?Model $source = null, ?int $userId = null): void
    {
        $employee->history()->create([
            'source_type' => $source ? $source::class : null,
            'source_id' => $source?->getKey(),
            'event' => $event,
            'title' => $title,
            'effective_date' => $newValues['effective_date'] ?? null,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'created_by' => $userId,
        ]);
    }
}
