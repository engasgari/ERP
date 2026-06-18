<?php

namespace App\Http\Controllers;

use App\Models\InventoryDocument;
use App\Models\Item;
use App\Models\Project;
use App\Models\Warehouse;
use App\Services\InventoryPostingService;
use App\Services\AccountingPostingService;
use App\Services\NumberingService;
use App\Services\RelatedDocumentDeletionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryDocumentController extends Controller
{
    public function index(Request $request)
    {
        $query = InventoryDocument::with([
            'warehouse',
            'targetWarehouse',
            'project',
            'creator',
            'confirmer',
            'source',
            'accountingDocument',
            'lines.item.unit',
        ])->latest();

        foreach (['type', 'status', 'entry_mode', 'warehouse_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->{$filter});
            }
        }

        if ($request->filled('search')) {
            $query->where(fn ($q) => $q
                ->where('number', 'like', '%' . $request->search . '%')
                ->orWhere('description', 'like', '%' . $request->search . '%')
                ->orWhereHas('lines.item', fn ($lineQuery) => $lineQuery->where('name', 'like', '%' . $request->search . '%'))
            );
        }

        return view('inventory-documents.index', [
            'documents' => $query->paginate(20)->withQueryString(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return view('inventory-documents.form', [
            'document' => new InventoryDocument([
                'type' => $request->get('type', 'receipt'),
                'document_date' => now()->toDateString(),
                'document_time' => now()->format('H:i'),
                'status' => 'draft',
            ]),
            'items' => Item::with('unit')->where('type', 'product')->where('is_active', true)->orderBy('name')->get(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'isEdit' => false,
        ]);
    }

    public function store(Request $request, NumberingService $numbering, InventoryPostingService $inventory, AccountingPostingService $posting)
    {
        $validated = $this->validateDocument($request);

        try {
            $document = DB::transaction(function () use ($validated, $numbering, $inventory, $posting) {
                if (in_array($validated['type'], ['issue', 'transfer', 'consumption'], true)) {
                    $this->assertManualIssueStock($inventory, $validated);
                }

                $document = InventoryDocument::create([
                    'number' => $numbering->next($this->numberingKey($validated['type']), $this->numberingPrefix($validated['type'])),
                    'type' => $validated['type'],
                    'document_date' => $validated['document_date'],
                    'document_time' => $validated['document_time'] ?? now()->format('H:i:s'),
                    'warehouse_id' => $validated['warehouse_id'],
                    'target_warehouse_id' => $validated['target_warehouse_id'] ?? null,
                    'project_id' => $validated['project_id'] ?? null,
                    'entry_mode' => 'manual',
                    'status' => $validated['status'],
                    'description' => $validated['description'] ?? null,
                    'created_by' => auth()->id(),
                    'confirmed_by' => $validated['status'] === 'confirmed' ? auth()->id() : null,
                    'confirmed_at' => $validated['status'] === 'confirmed' ? now() : null,
                ]);

                $this->syncLines($document, $validated['lines']);

                if ($document->status === 'confirmed') {
                    $posting->fromInventoryDocument($document, auth()->id());
                }

                return $document;
            });
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['lines' => $exception->getMessage()]);
        }

        return redirect()->route('inventory-documents.index')->with('success', 'سند انبار ثبت شد.');
    }

    public function edit(InventoryDocument $inventoryDocument)
    {
        abort_if($inventoryDocument->is_automatic, 403, 'اسناد اتوماتیک فقط از سرمنشا قابل تغییر هستند.');

        return view('inventory-documents.form', [
            'document' => $inventoryDocument->load('lines.item.unit'),
            'items' => Item::with('unit')->where('type', 'product')->where('is_active', true)->orderBy('name')->get(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, InventoryDocument $inventoryDocument, InventoryPostingService $inventory, AccountingPostingService $posting)
    {
        abort_if($inventoryDocument->is_automatic, 403, 'اسناد اتوماتیک فقط از سرمنشا قابل تغییر هستند.');

        $validated = $this->validateDocument($request);

        try {
            DB::transaction(function () use ($validated, $inventoryDocument, $inventory, $posting) {
                if (in_array($validated['type'], ['issue', 'transfer', 'consumption'], true)) {
                    $this->assertManualIssueStock($inventory, $validated, $inventoryDocument->id);
                }

                $inventoryDocument->update([
                    'type' => $validated['type'],
                    'document_date' => $validated['document_date'],
                    'document_time' => $validated['document_time'] ?? now()->format('H:i:s'),
                    'warehouse_id' => $validated['warehouse_id'],
                    'target_warehouse_id' => $validated['target_warehouse_id'] ?? null,
                    'project_id' => $validated['project_id'] ?? null,
                    'status' => $validated['status'],
                    'description' => $validated['description'] ?? null,
                    'confirmed_by' => $validated['status'] === 'confirmed' ? (auth()->id() ?: $inventoryDocument->confirmed_by) : null,
                    'confirmed_at' => $validated['status'] === 'confirmed' ? ($inventoryDocument->confirmed_at ?: now()) : null,
                ]);

                $inventoryDocument->lines()->delete();
                $this->syncLines($inventoryDocument, $validated['lines']);

                if ($inventoryDocument->status === 'confirmed' && !$inventoryDocument->accounting_document_id) {
                    $posting->fromInventoryDocument($inventoryDocument, auth()->id());
                }
            });
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['lines' => $exception->getMessage()]);
        }

        return redirect()->route('inventory-documents.index')->with('success', 'سند انبار ویرایش شد.');
    }

    public function destroy(InventoryDocument $inventoryDocument, RelatedDocumentDeletionService $deletion)
    {
        if ($inventoryDocument->is_automatic) {
            $source = $inventoryDocument->source_type
                ? class_basename($inventoryDocument->source_type) . ' #' . $inventoryDocument->source_id
                : 'ثبت سیستمی';

            return redirect()->route('inventory-documents.index')
                ->with('error', 'اسناد اتوماتیک فقط از سرمنشا قابل حذف هستند.')
                ->with('error_details', [
                    'سند انبار: ' . $inventoryDocument->number,
                    'منبع مرتبط: ' . $source,
                    'برای حذف، سند مادر را بررسی یا حذف کنید.',
                ]);
        }

        $deletion->deleteInventoryDocumentWithRelated($inventoryDocument);

        return redirect()->route('inventory-documents.index')->with('success', 'سند انبار حذف شد.');
    }

    private function validateDocument(Request $request): array
    {
        $request->merge(['document_date' => jalaliToGregorianDate($request->input('document_date')) ?: $request->input('document_date')]);

        $validated = $request->validate([
            'type' => 'required|in:receipt,issue,transfer,consumption',
            'document_date' => 'required|date',
            'document_time' => 'nullable|date_format:H:i',
            'warehouse_id' => 'required|exists:warehouses,id',
            'target_warehouse_id' => 'nullable|required_if:type,transfer|different:warehouse_id|exists:warehouses,id',
            'project_id' => 'nullable|exists:projects,id',
            'status' => 'required|in:draft,confirmed',
            'description' => 'nullable|string',
            'lines' => 'required|array',
            'lines.*.item_id' => 'nullable|exists:items,id',
            'lines.*.quantity' => 'nullable|numeric|min:0.001',
            'lines.*.unit_price' => 'nullable|numeric|min:0',
            'lines.*.description' => 'nullable|string|max:255',
        ]);

        $validated['lines'] = collect($validated['lines'])
            ->filter(fn ($line) => !empty($line['item_id']))
            ->values()
            ->all();

        if (empty($validated['lines'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['lines' => 'حداقل یک ردیف کالا وارد کنید.']);
        }

        return $validated;
    }

    private function syncLines(InventoryDocument $document, array $lines): void
    {
        foreach ($lines as $line) {
            $quantity = (float) ($line['quantity'] ?? 0);
            $unitPrice = (float) ($line['unit_price'] ?? 0);

            $document->lines()->create([
                'item_id' => $line['item_id'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $quantity * $unitPrice,
                'description' => $line['description'] ?? null,
            ]);
        }
    }

    private function assertManualIssueStock(InventoryPostingService $inventory, array $validated, ?int $ignoreDocumentId = null): void
    {
        if (($validated['status'] ?? 'draft') !== 'confirmed' || !in_array($validated['type'] ?? null, ['issue', 'transfer', 'consumption'], true)) {
            return;
        }

        foreach (collect($validated['lines'])->groupBy('item_id') as $itemId => $lines) {
            $required = $lines->sum(fn ($line) => (float) ($line['quantity'] ?? 0));
            $available = $inventory->availableQuantity((int) $itemId, (int) $validated['warehouse_id'], $ignoreDocumentId);

            if ($available + 0.0001 < $required) {
                $itemName = Item::find($itemId)?->name ?: 'کالا';

                throw new RuntimeException("موجودی {$itemName} کافی نیست. موجودی فعلی: {$available}، مقدار حواله: {$required}");
            }
        }
    }

    private function numberingKey(string $type): string
    {
        return match ($type) {
            'receipt' => 'inventory_receipt',
            'issue' => 'inventory_issue',
            'consumption' => 'inventory_consumption',
            'transfer' => 'inventory_transfer',
        };
    }

    private function numberingPrefix(string $type): string
    {
        return match ($type) {
            'receipt' => 'IR-',
            'issue' => 'II-',
            'consumption' => 'IC-',
            'transfer' => 'IT-',
        };
    }
}
