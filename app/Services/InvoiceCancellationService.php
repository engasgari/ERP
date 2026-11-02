<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InvoiceCancellationService
{
    /**
     * Mark sale/purchase invoices and proformas as cancelled.
     * Confirmed documents keep posted inventory/accounting; register a linked return
     * when the commercial reverse (برگشت) is needed.
     */
    public function cancel(Invoice $invoice, ?int $userId = null): Invoice
    {
        if ($invoice->isReturn()) {
            throw new RuntimeException('فاکتور برگشت از این مسیر ابطال نمی‌شود.');
        }

        if ($invoice->status === 'cancelled') {
            throw new RuntimeException('این سند قبلاً ابطال شده است.');
        }

        return DB::transaction(function () use ($invoice) {
            $invoice->update([
                'status' => 'cancelled',
                'settled_at' => null,
                'settled_by' => null,
            ]);

            return $invoice->fresh(['party', 'project', 'lines.item']);
        });
    }

    /**
     * Cancelled sale/purchase invoices available as return sources.
     *
     * @return \Illuminate\Support\Collection<int, Invoice>
     */
    public function cancelledSourcesForReturn(string $direction, ?int $ignoreReturnId = null)
    {
        return Invoice::query()
            ->with(['party', 'warehouse', 'project', 'lines.item.unit'])
            ->where('direction', $direction)
            ->where('document_type', 'invoice')
            ->where('status', 'cancelled')
            ->whereDoesntHave('returnInvoices', function ($query) use ($ignoreReturnId) {
                $query->where('status', '!=', 'cancelled');

                if ($ignoreReturnId) {
                    $query->where('id', '!=', $ignoreReturnId);
                }
            })
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->get();
    }

    public function assertValidReturnSource(Invoice $source, string $direction, ?int $ignoreReturnId = null): void
    {
        if ($source->document_type !== 'invoice') {
            throw new RuntimeException('فقط فاکتور فروش/خرید ابطال‌شده قابل انتخاب برای برگشت است.');
        }

        if ($source->status !== 'cancelled') {
            throw new RuntimeException('ابتدا فاکتور مبنا را ابطال کنید، سپس برگشت را ثبت کنید.');
        }

        if ($source->direction !== $direction) {
            throw new RuntimeException('جهت فاکتور ابطالی با برگشت یکسان نیست.');
        }

        $existingReturn = Invoice::query()
            ->where('returned_from_id', $source->id)
            ->where('status', '!=', 'cancelled')
            ->when($ignoreReturnId, fn ($query) => $query->where('id', '!=', $ignoreReturnId))
            ->exists();

        if ($existingReturn) {
            throw new RuntimeException('برای این فاکتور ابطالی قبلاً برگشت فعال ثبت شده است.');
        }
    }
}
