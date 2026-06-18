<?php

namespace App\Services;

use App\Models\EmploymentContract;
use App\Models\EmploymentOrder;

class ContractGenerationService
{
    public function fromEmploymentOrder(EmploymentOrder $order, ?int $userId = null): EmploymentContract
    {
        $order->loadMissing(['employee.party', 'position', 'organizationUnit']);

        $contract = EmploymentContract::withTrashed()
            ->firstOrNew(['employment_order_id' => $order->id]);

        if ($contract->exists && $contract->trashed()) {
            $contract->restore();
        }

        $contract->fill([
            'number' => 'CON-' . str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
            'employee_id' => $order->employee_id,
            'contract_type' => $order->employment_type === 'hourly' ? 'hourly' : 'monthly',
            'start_date' => $order->effective_date,
            'end_date' => $order->end_date,
            'body' => $this->defaultBody($order),
            'status' => 'draft',
            'created_by' => $userId,
        ]);

        $contract->save();

        return $contract;
    }

    private function defaultBody(EmploymentOrder $order): string
    {
        $employeeName = $order->employee->full_name;
        $position = $order->position?->title ?: 'تعیین نشده';
        $unit = $order->organizationUnit?->title ?: 'تعیین نشده';
        $start = formatJalaliDateSafe($order->effective_date);
        $end = formatJalaliDateSafe($order->end_date, 'نامحدود');

        return "این قرارداد بین شرکت و {$employeeName} از تاریخ {$start} تا {$end} برای سمت {$position} در واحد {$unit} تنظیم می‌شود.\n\nمحل امضای کارمند:\n\nمحل امضای کارفرما:\n\nمحل امضای شاهد:";
    }
}
