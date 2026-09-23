<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceContractorAllocation;
use App\Models\Party;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContractorServicePurchaseService
{
    public function __construct(
        private readonly FiscalPeriodService $fiscalPeriods,
        private readonly NumberingService $numbering,
    ) {}

    public function assignContractor(Invoice $saleInvoice, int $contractorPartyId, User $actor): InvoiceContractorAllocation
    {
        $this->assertEligibleSaleInvoice($saleInvoice);

        if ($saleInvoice->contractorAllocation?->purchase_invoice_id) {
            throw ValidationException::withMessages([
                'contractor' => 'برای این فاکتور فروش، فاکتور خرید پیمانکار قبلاً صادر شده است.',
            ]);
        }

        $contractor = Party::query()
            ->where('id', $contractorPartyId)
            ->where('is_active', true)
            ->whereHas('types', fn ($q) => $q->whereIn('name', ['contractor', 'vendor']))
            ->first();

        if (! $contractor) {
            throw ValidationException::withMessages([
                'contractor' => 'پیمانکار معتبر انتخاب نشده است.',
            ]);
        }

        return InvoiceContractorAllocation::query()->updateOrCreate(
            ['sale_invoice_id' => $saleInvoice->id],
            [
                'contractor_party_id' => $contractor->id,
                'updated_by' => $actor->id,
                'created_by' => $saleInvoice->contractorAllocation?->created_by ?? $actor->id,
            ],
        );
    }

    /**
     * @param  list<int>  $saleInvoiceIds
     */
    public function createPurchaseFromSales(array $saleInvoiceIds, User $actor): Invoice
    {
        $saleInvoiceIds = collect($saleInvoiceIds)->map(fn ($id) => (int) $id)->unique()->values()->all();

        if ($saleInvoiceIds === []) {
            throw ValidationException::withMessages([
                'selection' => 'حداقل یک فاکتور فروش انتخاب کنید.',
            ]);
        }

        $sales = Invoice::query()
            ->whereIn('id', $saleInvoiceIds)
            ->with(['lines.item', 'contractorAllocation', 'project'])
            ->get();

        if ($sales->count() !== count($saleInvoiceIds)) {
            throw ValidationException::withMessages(['selection' => 'برخی فاکتورهای انتخاب‌شده یافت نشد.']);
        }

        foreach ($sales as $sale) {
            $this->assertEligibleSaleInvoice($sale);

            if (! $sale->contractorAllocation?->contractor_party_id) {
                throw ValidationException::withMessages([
                    'selection' => "برای فاکتور {$sale->number} پیمانکار مشخص نشده است.",
                ]);
            }

            if ($sale->contractorAllocation->purchase_invoice_id) {
                throw ValidationException::withMessages([
                    'selection' => "فاکتور فروش {$sale->number} قبلاً در فاکتور خرید ثبت شده است.",
                ]);
            }
        }

        $contractorIds = $sales->pluck('contractorAllocation.contractor_party_id')->unique();
        if ($contractorIds->count() !== 1) {
            throw ValidationException::withMessages([
                'selection' => 'همه فاکتورهای انتخاب‌شده باید به یک پیمانکار تعلق داشته باشند.',
            ]);
        }

        $projectIds = $sales->pluck('project_id')->filter()->unique();
        if ($projectIds->count() > 1) {
            throw ValidationException::withMessages([
                'selection' => 'همه فاکتورهای انتخاب‌شده باید متعلق به یک پروژه باشند.',
            ]);
        }

        $aggregatedLines = $this->aggregateServiceLines($sales);
        if ($aggregatedLines->isEmpty()) {
            throw ValidationException::withMessages([
                'selection' => 'ردیف خدمتی برای تجمیع یافت نشد.',
            ]);
        }

        $contractorPartyId = (int) $contractorIds->first();
        $invoiceDate = $sales->max('invoice_date')?->toDateString() ?? now()->toDateString();
        $projectId = $projectIds->first();
        $saleNumbers = $sales->pluck('number')->filter()->implode('، ');

        return DB::transaction(function () use (
            $aggregatedLines,
            $contractorPartyId,
            $invoiceDate,
            $projectId,
            $saleNumbers,
            $sales,
            $actor,
        ) {
            $fiscalYear = $this->fiscalPeriods->fiscalYearForDate($invoiceDate);

            $purchase = Invoice::create([
                'fiscal_year_id' => $fiscalYear?->id,
                'direction' => 'purchase',
                'document_type' => 'invoice',
                'number' => $this->numbering->next('purchase_invoice', null, $fiscalYear?->id),
                'invoice_date' => $invoiceDate,
                'party_id' => $contractorPartyId,
                'project_id' => $projectId,
                'warehouse_id' => null,
                'status' => 'draft',
                'subtotal' => 0,
                'discount_amount' => 0,
                'tax_rate' => 0,
                'tax_amount' => 0,
                'total_amount' => 0,
                'description' => 'خرید خدمات پیمانکار — تجمیع فاکتورهای فروش: ' . $saleNumbers,
                'created_by' => $actor->id,
            ]);

            foreach ($aggregatedLines as $row) {
                $purchase->lines()->create([
                    'item_id' => $row['item_id'],
                    'description' => $row['description'],
                    'quantity' => $row['quantity'],
                    'unit_price' => 0,
                    'discount_amount' => 0,
                    'tax_rate' => 0,
                    'tax_amount' => 0,
                    'line_total' => 0,
                ]);
            }

            $purchase->refresh();
            $purchase->update([
                'subtotal' => 0,
                'total_amount' => 0,
            ]);

            foreach ($sales as $sale) {
                InvoiceContractorAllocation::query()
                    ->where('sale_invoice_id', $sale->id)
                    ->update([
                        'purchase_invoice_id' => $purchase->id,
                        'updated_by' => $actor->id,
                    ]);
            }

            return $purchase->fresh(['party', 'lines.item', 'project']);
        });
    }

    private function assertEligibleSaleInvoice(Invoice $invoice): void
    {
        if ($invoice->direction !== 'sale' || $invoice->document_type !== 'invoice') {
            throw ValidationException::withMessages([
                'invoice' => 'فقط فاکتور فروش قابل تخصیص است.',
            ]);
        }

        if ($invoice->status !== 'confirmed') {
            throw ValidationException::withMessages([
                'invoice' => 'فاکتور فروش باید تأیید شده باشد.',
            ]);
        }

        $invoice->loadMissing('fiscalYear');

        if (! $invoice->fiscalYear || $invoice->fiscalYear->status !== 'open') {
            throw ValidationException::withMessages([
                'invoice' => 'فاکتور مربوط به سال مالی بسته است و قابل تخصیص نیست.',
            ]);
        }

        $hasService = $invoice->lines()
            ->whereHas('item', fn ($q) => $q->where('type', 'service'))
            ->exists();

        if (! $hasService) {
            throw ValidationException::withMessages([
                'invoice' => 'فاکتور باید حداقل یک ردیف خدمت داشته باشد.',
            ]);
        }
    }

    /**
     * @param  Collection<int, Invoice>  $sales
     * @return Collection<int, array{item_id: int, quantity: float, description: string}>
     */
    private function aggregateServiceLines(Collection $sales): Collection
    {
        /** @var array<int, array{item_id: int, quantity: float, sources: list<string>}> $bucket */
        $bucket = [];

        foreach ($sales as $sale) {
            foreach ($sale->lines as $line) {
                if ($line->item?->type !== 'service' || ! $line->item_id) {
                    continue;
                }

                $itemId = (int) $line->item_id;

                if (! isset($bucket[$itemId])) {
                    $bucket[$itemId] = [
                        'item_id' => $itemId,
                        'quantity' => 0.0,
                        'sources' => [],
                    ];
                }

                $bucket[$itemId]['quantity'] += (float) $line->quantity;
                $bucket[$itemId]['sources'][] = (string) $sale->number;
            }
        }

        return collect($bucket)->map(function (array $row) {
            $sources = collect($row['sources'])->unique()->values()->implode('، ');

            return [
                'item_id' => $row['item_id'],
                'quantity' => round($row['quantity'], 3),
                'description' => 'تجمیع فروش — فاکتورها: ' . $sources,
            ];
        })->values();
    }
}
