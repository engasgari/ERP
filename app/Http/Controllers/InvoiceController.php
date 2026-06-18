<?php

namespace App\Http\Controllers;

use App\Models\AccountingDocument;
use App\Models\CompanySetting;
use App\Models\InventoryDocument;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Party;
use App\Models\PartyType;
use App\Models\Warehouse;
use App\Services\AccountingDocumentService;
use App\Services\InventoryPostingService;
use App\Services\InvoiceExcelTemplateService;
use App\Services\NumberingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Invoice::with('party', 'lines.item')->latest();

        foreach (['direction', 'document_type', 'status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->{$filter});
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn ($q) => $q
                ->where('number', 'like', '%' . $search . '%')
                ->orWhere('description', 'like', '%' . $search . '%')
                ->orWhereHas('party', fn ($partyQuery) => $partyQuery->where('name', 'like', '%' . $search . '%'))
                ->orWhereHas('lines.item', fn ($lineQuery) => $lineQuery->where('name', 'like', '%' . $search . '%'))
            );
        }

        return view('invoices.index', [
            'invoices' => $query->paginate(15)->withQueryString(),
        ]);
    }

    public function create(Request $request)
    {
        return view('invoices.create', $this->formData(
            direction: $request->get('direction', 'sale'),
            documentType: $request->get('document_type', 'invoice')
        ));
    }

    public function edit(Invoice $invoice)
    {
        return view('invoices.create', $this->formData(
            direction: $invoice->direction,
            documentType: $invoice->document_type,
            invoice: $invoice->load('party', 'lines')
        ));
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['party.types', 'warehouse', 'lines.item.unit', 'accountingDocument.lines', 'inventoryDocuments.lines']);

        return view('invoices.show', [
            'invoice' => $invoice,
            'company' => CompanySetting::first(),
        ]);
    }

    public function print(Invoice $invoice)
    {
        $invoice->load(['party.types', 'warehouse', 'lines.item.unit', 'inventoryDocuments.lines']);

        return view('invoices.print', [
            'invoice' => $invoice,
            'company' => CompanySetting::first(),
        ]);
    }

    public function downloadPdf(Invoice $invoice)
    {
        $invoice->load(['party.types', 'warehouse', 'lines.item.unit', 'inventoryDocuments.lines']);

        $pdf = Pdf::loadView('invoices.print', [
            'invoice' => $invoice,
            'company' => CompanySetting::first(),
            'forPdf' => true,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('invoice-' . $invoice->number . '.pdf');
    }

    public function downloadExcel(Invoice $invoice, InvoiceExcelTemplateService $excel)
    {
        $invoice->load(['party', 'lines.item.unit']);
        $path = $excel->build($invoice, CompanySetting::first());

        return response()->download($path, 'invoice-' . $invoice->number . '.xlsx')->deleteFileAfterSend(true);
    }

    public function store(Request $request, NumberingService $numbering, InventoryPostingService $inventory)
    {
        $payload = $this->validatedPayload($request);

        if ($message = $this->stockErrorMessage($payload, $inventory)) {
            return back()->withInput()->withErrors(['lines' => $message]);
        }

        $key = $payload['data']['document_type'] === 'proforma'
            ? 'sale_proforma'
            : ($payload['data']['direction'] === 'sale' ? 'sale_invoice' : 'purchase_invoice');

        $invoice = DB::transaction(function () use ($payload, $numbering, $key) {
            $invoice = Invoice::create([
                'direction' => $payload['data']['direction'],
                'document_type' => $payload['data']['document_type'],
                'number' => $payload['data']['number'] ?: $numbering->next($key),
                'invoice_date' => $payload['data']['invoice_date'],
                'party_id' => $payload['data']['party_id'],
                'warehouse_id' => $payload['data']['warehouse_id'] ?? null,
                'status' => 'draft',
                'subtotal' => $payload['subtotal'],
                'discount_amount' => $payload['discount_amount'],
                'tax_rate' => 0,
                'tax_amount' => $payload['tax_amount'],
                'total_amount' => $payload['total_amount'],
                'description' => $payload['data']['description'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $this->syncLines($invoice, $payload['lines']);

            return $invoice;
        });

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', 'فاکتور/پیش‌فاکتور ثبت موقت شد.');
    }

    public function update(Request $request, Invoice $invoice, InventoryPostingService $inventory)
    {
        $payload = $this->validatedPayload($request, $invoice);

        try {
            DB::transaction(function () use ($invoice, $payload, $inventory) {
                $this->deleteDownstreamDocuments($invoice);

                if ($message = $this->stockErrorMessage($payload, $inventory)) {
                    throw new RuntimeException($message);
                }

                $invoice->update([
                    'direction' => $payload['data']['direction'],
                    'document_type' => $payload['data']['document_type'],
                    'number' => $payload['data']['number'] ?: $invoice->number,
                    'invoice_date' => $payload['data']['invoice_date'],
                    'party_id' => $payload['data']['party_id'],
                    'warehouse_id' => $payload['data']['warehouse_id'] ?? null,
                    'status' => 'draft',
                    'subtotal' => $payload['subtotal'],
                    'discount_amount' => $payload['discount_amount'],
                    'tax_rate' => 0,
                    'tax_amount' => $payload['tax_amount'],
                    'total_amount' => $payload['total_amount'],
                    'description' => $payload['data']['description'] ?? null,
                    'accounting_document_id' => null,
                    'confirmed_at' => null,
                ]);

                $this->syncLines($invoice, $payload['lines']);
            });
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['lines' => $exception->getMessage()]);
        }

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', 'فاکتور ویرایش شد. اسناد قبلی انبار و مالی حذف شدند؛ برای صدور سند جدید دوباره تایید کنید.');
    }

    public function preview(Request $request)
    {
        $payload = $this->validatedPayload($request, allowDuplicateNumber: true);
        $itemIds = $payload['lines']->pluck('item_id')->filter()->unique();
        $items = Item::with('unit')->whereIn('id', $itemIds)->get()->keyBy('id');

        $lines = $payload['lines']
            ->map(fn ($line) => (object) [
                'item_id' => $line['item_id'],
                'item' => $items->get($line['item_id']),
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'discount_amount' => $line['discount_amount'],
                'tax_rate' => $line['tax_rate'],
                'tax_amount' => $line['tax_amount'],
                'line_total' => $line['line_total'],
            ])
            ->values();

        $invoice = (object) [
            'id' => null,
            'direction' => $payload['data']['direction'],
            'document_type' => $payload['data']['document_type'],
            'number' => $payload['data']['number'] ?: 'پیش‌نمایش',
            'invoice_date' => $payload['data']['invoice_date'],
            'party' => Party::findOrFail($payload['data']['party_id']),
            'lines' => $lines,
            'status' => 'draft',
            'discount_amount' => $payload['discount_amount'],
            'tax_amount' => $payload['tax_amount'],
            'total_amount' => $payload['total_amount'],
            'description' => $payload['data']['description'] ?? null,
        ];

        return view('invoices.print', [
            'invoice' => $invoice,
            'company' => CompanySetting::first(),
            'preview' => true,
        ]);
    }

    public function confirm(Invoice $invoice, AccountingDocumentService $documents)
    {
        abort_if($invoice->document_type === 'proforma', 422, 'پیش‌فاکتور مستقیم تایید مالی نمی‌شود.');

        try {
            $documents->postInvoice($invoice);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['inventory' => $exception->getMessage()]);
        }

        return redirect()->route('invoices.show', $invoice)->with('success', 'فاکتور تایید و سند حسابداری اتومات صادر شد.');
    }

    public function convert(Invoice $invoice, NumberingService $numbering)
    {
        abort_if($invoice->document_type !== 'proforma', 422);

        $new = $invoice->replicate(['number', 'document_type', 'status', 'accounting_document_id', 'confirmed_at']);
        $new->number = $numbering->next($invoice->direction === 'sale' ? 'sale_invoice' : 'purchase_invoice');
        $new->document_type = 'invoice';
        $new->status = 'draft';
        $new->converted_from_id = $invoice->id;
        $new->save();

        foreach ($invoice->lines as $line) {
            $new->lines()->create($line->only(['item_id', 'description', 'quantity', 'unit_price', 'discount_amount', 'tax_rate', 'tax_amount', 'line_total']));
        }

        return redirect()->route('invoices.show', $new)->with('success', 'پیش‌فاکتور به فاکتور تبدیل شد.');
    }

    public function destroy(Invoice $invoice)
    {
        DB::transaction(function () use ($invoice) {
            $this->deleteDownstreamDocuments($invoice);
            $invoice->lines()->delete();
            $invoice->delete();
        });

        return redirect()->route('invoices.index')->with('success', 'فاکتور و همه اسناد انبار و مالی وابسته حذف شدند.');
    }

    private function formData(string $direction, string $documentType, ?Invoice $invoice = null): array
    {
        $partyType = $direction === 'purchase' ? 'vendor' : 'customer';
        $parties = Party::where('is_active', true)
            ->whereHas('types', fn ($query) => $query->where('name', $partyType))
            ->orderBy('name')
            ->get();

        if ($parties->isEmpty()) {
            $parties = Party::where('is_active', true)->orderBy('name')->get();
        }

        if ($invoice?->party && !$parties->contains('id', $invoice->party_id)) {
            $parties->push($invoice->party);
        }

        return [
            'invoice' => $invoice,
            'isEdit' => (bool) $invoice,
            'direction' => $direction,
            'documentType' => $documentType,
            'parties' => $parties,
            'items' => Item::with('unit')->where('is_active', true)->orderBy('name')->get(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
            'partyTypeId' => PartyType::where('name', $partyType)->value('id'),
        ];
    }

    private function validatedPayload(Request $request, ?Invoice $invoice = null, bool $allowDuplicateNumber = false): array
    {
        $request->merge(['invoice_date' => $this->normalizeInvoiceDate($request->input('invoice_date'))]);

        $numberRule = 'nullable|string|max:255';
        if (!$allowDuplicateNumber) {
            $numberRule .= '|unique:invoices,number' . ($invoice ? ',' . $invoice->id : '');
        }

        $validated = $request->validate([
            'direction' => 'required|in:sale,purchase',
            'document_type' => 'required|in:proforma,invoice',
            'number' => $numberRule,
            'invoice_date' => 'required|date',
            'party_id' => 'required|exists:parties,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'description' => 'nullable|string',
            'lines' => 'required|array',
            'lines.*.item_id' => 'nullable|exists:items,id',
            'lines.*.description' => 'nullable|string|max:255',
            'lines.*.quantity' => 'nullable|numeric|min:0.001',
            'lines.*.unit_price' => 'nullable|numeric|min:0',
            'lines.*.discount_amount' => 'nullable|numeric|min:0',
            'lines.*.tax_rate' => 'nullable|numeric|min:0',
        ]);

        $lines = collect($validated['lines'])
            ->filter(fn ($line) => !empty($line['item_id']))
            ->map(function ($line) {
                $quantity = (float) ($line['quantity'] ?? 0);
                $unitPrice = (float) ($line['unit_price'] ?? 0);
                $discountAmount = (float) ($line['discount_amount'] ?? 0);
                $taxRate = (float) ($line['tax_rate'] ?? 0);
                $baseAmount = $quantity * $unitPrice;
                $taxableAmount = max($baseAmount - $discountAmount, 0);
                $taxAmount = $taxableAmount * ($taxRate / 100);

                return [
                    'item_id' => (int) $line['item_id'],
                    'description' => $line['description'] ?? null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $discountAmount,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'line_total' => $taxableAmount + $taxAmount,
                    'base_amount' => $baseAmount,
                ];
            })
            ->values();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => 'حداقل یک ردیف کالا یا خدمت وارد کنید.']);
        }

        $subtotal = $lines->sum('base_amount');
        $discountAmount = $lines->sum('discount_amount');
        $taxAmount = $lines->sum('tax_amount');

        return [
            'data' => $validated,
            'lines' => $lines,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'total_amount' => $subtotal - $discountAmount + $taxAmount,
        ];
    }

    private function syncLines(Invoice $invoice, $lines): void
    {
        $invoice->lines()->delete();

        foreach ($lines as $line) {
            unset($line['base_amount']);
            $invoice->lines()->create($line);
        }
    }

    private function stockErrorMessage(array $payload, InventoryPostingService $inventory): ?string
    {
        $lines = $payload['lines'];
        $validated = $payload['data'];
        $productItemIds = Item::whereIn('id', $lines->pluck('item_id'))->where('type', 'product')->pluck('id');

        if ($validated['document_type'] === 'invoice' && $productItemIds->isNotEmpty() && empty($validated['warehouse_id'])) {
            return 'برای فاکتور دارای کالا، انتخاب انبار الزامی است.';
        }

        if ($validated['direction'] !== 'sale' || $validated['document_type'] !== 'invoice' || $productItemIds->isEmpty()) {
            return null;
        }

        $required = $lines
            ->whereIn('item_id', $productItemIds)
            ->groupBy('item_id')
            ->map(fn ($group) => $group->sum('quantity'));

        foreach ($required as $itemId => $quantity) {
            $available = $inventory->availableQuantity((int) $itemId, (int) $validated['warehouse_id']);

            if ($available + 0.0001 < $quantity) {
                $itemName = Item::find($itemId)?->name ?: 'کالا';

                return "موجودی {$itemName} کافی نیست. موجودی فعلی: {$available}، مقدار فاکتور: {$quantity}";
            }
        }

        return null;
    }

    private function deleteDownstreamDocuments(Invoice $invoice): void
    {
        $inventoryDocuments = InventoryDocument::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->get();

        $inventoryIds = $inventoryDocuments->pluck('id');
        $accountingIds = collect([$invoice->accounting_document_id])
            ->merge($inventoryDocuments->pluck('accounting_document_id'))
            ->merge(AccountingDocument::withTrashed()->where('source_type', Invoice::class)->where('source_id', $invoice->id)->pluck('id'));

        if ($inventoryIds->isNotEmpty()) {
            $accountingIds = $accountingIds->merge(
                AccountingDocument::withTrashed()
                    ->where('source_type', InventoryDocument::class)
                    ->whereIn('source_id', $inventoryIds)
                    ->pluck('id')
            );
        }

        foreach ($inventoryDocuments as $document) {
            $document->lines()->delete();
            $document->delete();
        }

        foreach ($accountingIds->filter()->unique() as $accountingId) {
            $document = AccountingDocument::withTrashed()->find($accountingId);
            if (!$document) {
                continue;
            }

            $document->lines()->delete();
            $document->forceDelete();
        }
    }

    private function normalizeInvoiceDate(?string $value): ?string
    {
        $value = trim((string) normalizePersianDigits($value));

        if ($value === '') {
            return null;
        }

        $year = (int) substr(str_replace('/', '-', $value), 0, 4);

        if ($year >= 1700) {
            return str_replace('/', '-', $value);
        }

        return jalaliToGregorianDate($value);
    }
}
