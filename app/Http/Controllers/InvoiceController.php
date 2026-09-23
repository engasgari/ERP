<?php

namespace App\Http\Controllers;

use App\Models\AccountingDocument;
use App\Models\CompanySetting;
use App\Models\InventoryDocument;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Party;
use App\Models\PartyType;
use App\Models\Project;
use App\Models\Warehouse;
use App\Services\AccountingDocumentService;
use App\Services\FiscalPeriodService;
use App\Services\InventoryPostingService;
use App\Services\InvoiceCalculationService;
use App\Services\InvoiceExcelTemplateService;
use App\Services\ItemSalePriceService;
use App\Services\NumberingService;
use App\Support\PersianPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class InvoiceController extends Controller
{
    public function index()
    {
        return view('invoices.index');
    }

    public function create(Request $request)
    {
        return view('invoices.create', $this->formData(
            direction: $request->get('direction', 'sale'),
            documentType: $request->get('document_type', 'invoice')
        ));
    }

    public function edit(Request $request, Invoice $invoice)
    {
        $data = $this->formData(
            direction: $invoice->direction,
            documentType: $invoice->document_type,
            invoice: $invoice->load('party', 'lines')
        );

        if ($request->boolean('embedded')) {
            return view('invoices.edit-embedded', $data);
        }

        return view('invoices.create', $data);
    }

    /**
     * @return array<string, mixed>
     */
    public function embeddedEditFormData(Invoice $invoice): array
    {
        return $this->formData(
            direction: $invoice->direction,
            documentType: $invoice->document_type,
            invoice: $invoice->load('party', 'lines')
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function newEmbeddedFormData(string $direction, string $documentType): array
    {
        return $this->formData($direction, $documentType, null);
    }

    public function show(Request $request, Invoice $invoice)
    {
        $invoice->load(['party.types', 'project', 'warehouse', 'lines.item.unit', 'accountingDocument.lines', 'inventoryDocuments.lines', 'settledBy']);

        $data = [
            'invoice' => $invoice,
            'company' => CompanySetting::first(),
            'crmContext' => $request->boolean('crm'),
        ];

        if ($request->boolean('embedded')) {
            return view('invoices.show-embedded', $data);
        }

        return view('invoices.show', $data);
    }

    public function print(Invoice $invoice)
    {
        $invoice->load(['party.types', 'project', 'warehouse', 'lines.item.unit', 'inventoryDocuments.lines', 'settledBy']);

        return view('invoices.print', [
            'invoice' => $invoice,
            'company' => CompanySetting::first(),
        ]);
    }

    public function downloadPdf(Invoice $invoice)
    {
        $invoice->load(['party.types', 'project', 'warehouse', 'lines.item.unit', 'inventoryDocuments.lines', 'settledBy']);

        $pdf = PersianPdf::loadView('invoices.print', [
            'invoice' => $invoice,
            'company' => CompanySetting::first(),
        ], 'a4', 'landscape');

        return $pdf->download('invoice-' . $invoice->number . '.pdf');
    }

    public function downloadExcel(Invoice $invoice, InvoiceExcelTemplateService $excel)
    {
        $invoice->load(['party', 'project', 'lines.item.unit']);
        $path = $excel->build($invoice, CompanySetting::first());
        $filename = 'invoice-' . preg_replace('/[^\w\-]+/u', '_', (string) $invoice->number) . '.xlsx';

        return response()->download($path, $filename)->deleteFileAfterSend(true);
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
            $fiscalYear = app(FiscalPeriodService::class)->fiscalYearForDate($payload['data']['invoice_date']);

            $invoice = Invoice::create([
                'fiscal_year_id' => $fiscalYear?->id,
                'direction' => $payload['data']['direction'],
                'document_type' => $payload['data']['document_type'],
                'number' => ($payload['data']['number'] ?? null) ?: $numbering->next($key, null, $fiscalYear?->id),
                'invoice_date' => $payload['data']['invoice_date'],
                'party_id' => $payload['data']['party_id'],
                'project_id' => $payload['data']['project_id'] ?? null,
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

        if ($request->expectsJson()) {
            return $this->invoiceJsonPayload(
                $invoice->fresh(['party', 'lines.item.unit', 'project']),
                'فاکتور/پیش‌فاکتور ثبت موقت شد.'
            );
        }

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
                    'fiscal_year_id' => app(FiscalPeriodService::class)->fiscalYearForDate($payload['data']['invoice_date'])?->id,
                    'direction' => $payload['data']['direction'],
                    'document_type' => $payload['data']['document_type'],
                    'number' => $payload['data']['number'] ?: $invoice->number,
                    'invoice_date' => $payload['data']['invoice_date'],
                    'party_id' => $payload['data']['party_id'],
                    'project_id' => $payload['data']['project_id'] ?? null,
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
                    'settled_at' => null,
                    'settled_by' => null,
                ]);

                $this->syncLines($invoice, $payload['lines']);
            });
        } catch (RuntimeException $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'errors' => ['lines' => [$exception->getMessage()]],
                ], 422);
            }

            return back()->withInput()->withErrors(['lines' => $exception->getMessage()]);
        }

        if ($request->expectsJson()) {
            return $this->invoiceJsonPayload(
                $invoice->fresh(),
                'فاکتور ویرایش شد. اسناد قبلی انبار و مالی حذف شدند؛ برای صدور سند جدید دوباره تایید کنید.'
            );
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
            'project' => $payload['data']['project_id'] ? Project::find($payload['data']['project_id']) : null,
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

    public function confirm(Request $request, Invoice $invoice, AccountingDocumentService $documents)
    {
        abort_if($invoice->document_type === 'proforma', 422, 'پیش‌فاکتور مستقیم تایید مالی نمی‌شود.');

        try {
            $documents->postInvoice($invoice);
        } catch (RuntimeException $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'errors' => ['inventory' => [$exception->getMessage()]],
                ], 422);
            }

            return back()->withErrors(['inventory' => $exception->getMessage()]);
        }

        if ($request->expectsJson()) {
            return $this->invoiceJsonPayload($invoice->fresh(), 'فاکتور تایید و سند حسابداری اتومات صادر شد.');
        }

        return redirect()->route('invoices.show', $invoice)->with('success', 'فاکتور تایید و سند حسابداری اتومات صادر شد.');
    }

    public function settle(Request $request, Invoice $invoice)
    {
        abort_if($invoice->document_type === 'proforma', 422, 'پیش‌فاکتور قابل تسویه نیست.');
        abort_if($invoice->status !== 'confirmed', 422, 'فقط فاکتور تایید شده را می‌توان تسویه کرد.');

        if ($invoice->settled_at) {
            if ($request->expectsJson()) {
                return $this->invoiceJsonPayload($invoice, 'این فاکتور قبلاً تسویه شده است.');
            }

            return back()->with('success', 'این فاکتور قبلاً تسویه شده است.');
        }

        $invoice->update([
            'settled_at' => now(),
            'settled_by' => $request->user()?->id,
        ]);

        if ($request->expectsJson()) {
            return $this->invoiceJsonPayload($invoice->fresh(), 'فاکتور با موفقیت تسویه شد.');
        }

        return back()->with('success', 'فاکتور با موفقیت تسویه شد.');
    }

    public function unsettle(Request $request, Invoice $invoice)
    {
        abort_if($invoice->document_type === 'proforma', 422, 'پیش‌فاکتور قابل خروج از تسویه نیست.');
        abort_if(! $invoice->settled_at, 422, 'این فاکتور هنوز تسویه نشده است.');

        $invoice->update([
            'status' => 'confirmed',
            'settled_at' => null,
            'settled_by' => null,
        ]);

        if ($request->expectsJson()) {
            return $this->invoiceJsonPayload($invoice->fresh(), 'فاکتور از حالت تسویه خارج شد و به وضعیت تایید شده برگشت.');
        }

        return back()->with('success', 'فاکتور از حالت تسویه خارج شد و به وضعیت تایید شده برگشت.');
    }

    public function convert(Request $request, Invoice $invoice, NumberingService $numbering)
    {
        abort_if($invoice->document_type !== 'proforma', 422);

        $new = $invoice->replicate(['number', 'document_type', 'status', 'accounting_document_id', 'confirmed_at', 'settled_at', 'settled_by']);
        $new->number = $numbering->next(
            $invoice->direction === 'sale' ? 'sale_invoice' : 'purchase_invoice',
            null,
            $invoice->fiscal_year_id
        );
        $new->document_type = 'invoice';
        $new->status = 'draft';
        $new->converted_from_id = $invoice->id;
        $new->save();

        foreach ($invoice->lines as $line) {
            $new->lines()->create($line->only(['item_id', 'description', 'quantity', 'unit_price', 'discount_amount', 'tax_rate', 'tax_amount', 'line_total']));
        }

        if ($request->expectsJson()) {
            return $this->invoiceJsonPayload(
                $new->fresh(),
                'پیش‌فاکتور به فاکتور تبدیل شد.',
                reloadShowId: $new->id
            );
        }

        return redirect()->route('invoices.show', $new)->with('success', 'پیش‌فاکتور به فاکتور تبدیل شد.');
    }

    public function destroy(Request $request, Invoice $invoice)
    {
        DB::transaction(function () use ($invoice) {
            $this->deleteDownstreamDocuments($invoice);
            $invoice->lines()->delete();
            $invoice->delete();
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'فاکتور و همه اسناد انبار و مالی وابسته حذف شدند.',
                'deleted_id' => $invoice->id,
            ]);
        }

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
            'projects' => Project::query()->orderBy('name')->get(),
            'items' => Item::with('unit')->where('is_active', true)->orderBy('name')->get(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
            'partyTypeId' => PartyType::where('name', $partyType)->value('id'),
        ];
    }

    private function invoiceNumberRule(Request $request, ?Invoice $invoice = null, bool $allowDuplicateNumber = false): array|string
    {
        if ($allowDuplicateNumber) {
            return 'nullable|string|max:255';
        }

        $fiscalYearId = app(FiscalPeriodService::class)->fiscalYearForDate(
            $this->normalizeInvoiceDate($request->input('invoice_date'))
                ?? $invoice?->invoice_date?->toDateString()
        )?->id ?? $invoice?->fiscal_year_id;

        $rule = Rule::unique('invoices', 'number')->where(function ($query) use ($fiscalYearId) {
            if ($fiscalYearId) {
                $query->where('fiscal_year_id', $fiscalYearId);
            } else {
                $query->whereNull('fiscal_year_id');
            }
        });

        if ($invoice) {
            $rule->ignore($invoice->id);
        }

        return ['nullable', 'string', 'max:255', $rule];
    }

    private function validatedPayload(Request $request, ?Invoice $invoice = null, bool $allowDuplicateNumber = false): array
    {
        $request->merge([
            'invoice_date' => $this->normalizeInvoiceDate($request->input('invoice_date')),
            'lines' => collect($request->input('lines', []))->map(function ($line) {
                return [
                    ...$line,
                    'quantity' => $this->normalizeNumericInput($line['quantity'] ?? null),
                    'unit_price' => $this->normalizeNumericInput($line['unit_price'] ?? null),
                    'discount_amount' => $this->normalizeNumericInput($line['discount_amount'] ?? null),
                    'tax_rate' => $this->normalizeNumericInput($line['tax_rate'] ?? null),
                ];
            })->all(),
        ]);

        $numberRule = $this->invoiceNumberRule($request, $invoice, $allowDuplicateNumber);

        $validated = $request->validate([
            'direction' => 'required|in:sale,purchase',
            'document_type' => 'required|in:proforma,invoice',
            'number' => $numberRule,
            'invoice_date' => 'required|date',
            'party_id' => 'required|exists:parties,id',
            'project_id' => 'nullable|exists:projects,id',
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
            ->filter(fn ($line) => !empty($line['item_id']));

        $calculated = app(InvoiceCalculationService::class)->calculateDocument($lines);

        if ($calculated['lines'] === []) {
            throw ValidationException::withMessages(['lines' => 'حداقل یک ردیف کالا یا خدمت وارد کنید.']);
        }

        return [
            'data' => $validated,
            'lines' => collect($calculated['lines']),
            'subtotal' => $calculated['subtotal'],
            'discount_amount' => $calculated['discount_amount'],
            'tax_amount' => $calculated['tax_amount'],
            'total_amount' => $calculated['total_amount'],
        ];
    }

    private function syncLines(Invoice $invoice, $lines): void
    {
        $invoice->lines()->delete();

        foreach ($lines as $line) {
            unset($line['base_amount'], $line['taxable_amount']);
            $invoice->lines()->create($line);
        }

        app(ItemSalePriceService::class)->syncItemSalePricesFromInvoice($invoice->fresh(['lines']));
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
        $value = $this->normalizeNumericInput($value);

        if ($value === '') {
            return null;
        }

        $value = str_replace('-', '/', (string) $value);
        $year = (int) substr(str_replace('/', '-', $value), 0, 4);

        if ($year >= 1700) {
            return str_replace('/', '-', $value);
        }

        return jalaliToGregorianDate($value);
    }

    private function normalizeNumericInput(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $value = strtr($value, [
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
            ',' => '',
            '٬' => '',
            ' ' => '',
        ]);

        return str_replace('٫', '.', $value);
    }

    private function invoiceJsonPayload(Invoice $invoice, string $message, ?int $reloadShowId = null)
    {
        $invoice->loadMissing(['party', 'project']);

        return response()->json([
            'message' => $message,
            'reload_show_id' => $reloadShowId,
            'invoice' => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'type_label' => ($invoice->direction === 'sale' ? 'فروش' : 'خرید') . ' / ' . ($invoice->document_type === 'proforma' ? 'پیش‌فاکتور' : 'فاکتور'),
                'invoice_date' => gregorianToJalaliDate($invoice->invoice_date),
                'party_name' => $invoice->party?->name ?: 'طرف حساب حذف شده',
                'project_number' => $invoice->project?->project_number ?: '-',
                'total_amount_formatted' => formatMoney((float) $invoice->total_amount),
                'status' => $invoice->status,
                'settled_at' => $invoice->settled_at,
            ],
        ]);
    }
}
