<?php

namespace App\Http\Controllers;

use App\Models\AccountingDocument;
use App\Models\BomVersion;
use App\Models\InventoryDocument;
use App\Models\Item;
use App\Models\ProductionOrder;
use App\Models\Project;
use App\Models\Warehouse;
use App\Services\AccountingPostingService;
use App\Services\InventoryPostingService;
use App\Services\NumberingService;
use App\Services\ProjectCostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductionOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = ProductionOrder::with('project', 'item', 'bomVersion')
            ->withCount([
                'materialConsumptions',
                'materialConsumptions as consumed_materials_count' => fn ($query) => $query
                    ->whereNotNull('inventory_document_id'),
            ]);

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($query) use ($search) {
                $query->where('number', 'like', "%{$search}%")
                    ->orWhereHas('project', fn ($project) => $project->where('name', 'like', "%{$search}%")->orWhere('project_number', 'like', "%{$search}%"))
                    ->orWhereHas('item', fn ($item) => $item->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return view('production-orders.index', [
            'orders' => $query->latest()->paginate(15)->withQueryString(),
            'projects' => Project::orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $order = new ProductionOrder([
            'project_id' => $request->integer('project_id') ?: null,
            'quantity' => 1,
            'status' => 'draft',
        ]);

        return view('production-orders.form', $this->formData($order));
    }

    public function store(Request $request, NumberingService $numbering, ProjectCostingService $costing)
    {
        $data = $this->validated($request);

        $order = DB::transaction(function () use ($data, $request, $numbering, $costing) {
            $order = ProductionOrder::create($data + [
                'number' => $numbering->next('production_order', 'PO-'),
                'created_by' => $request->user()?->id,
            ]);

            if ($order->bom_version_id) {
                $bom = BomVersion::with('lines.component')->find($order->bom_version_id);
                foreach ($costing->bomPlannedLines($bom, (float) $order->quantity) as $line) {
                    $order->materialConsumptions()->create([
                        'item_id' => $line['item_id'],
                        'planned_quantity' => $line['quantity'],
                        'unit_cost' => $line['unit_cost'],
                        'status' => 'planned',
                    ]);
                }
            }

            return $order;
        });

        return redirect()->route('production-orders.show', $order)->with('success', 'سفارش تولید ثبت شد.');
    }

    public function show(ProductionOrder $productionOrder, ProjectCostingService $costing)
    {
        return view('production-orders.show', [
            'order' => $productionOrder->load('project', 'item', 'bomVersion', 'materialConsumptions.item', 'materialConsumptions.warehouse', 'materialConsumptions.inventoryDocument'),
            'summary' => $costing->productionSummary($productionOrder),
            'warehouses' => Warehouse::orderBy('name')->get(),
        ]);
    }

    public function edit(ProductionOrder $productionOrder)
    {
        return view('production-orders.form', $this->formData($productionOrder->load('materialConsumptions')));
    }

    public function update(Request $request, ProductionOrder $productionOrder)
    {
        $productionOrder->update($this->validated($request, editing: true));

        return redirect()->route('production-orders.show', $productionOrder)->with('success', 'سفارش تولید ویرایش شد.');
    }

    public function consume(
        Request $request,
        ProductionOrder $productionOrder,
        NumberingService $numbering,
        InventoryPostingService $inventory,
        AccountingPostingService $posting
    ) {
        $data = $request->validate([
            'lines' => 'required|array',
            'lines.*.id' => 'required|exists:production_material_consumptions,id',
            'lines.*.warehouse_id' => 'nullable|exists:warehouses,id',
            'lines.*.actual_quantity' => 'nullable|numeric|min:0',
            'lines.*.unit_cost' => 'nullable|numeric|min:0',
            'lines.*.status' => 'required|in:planned,reserved,consumed',
        ]);

        try {
            DB::transaction(function () use ($data, $productionOrder, $numbering, $inventory, $posting, $request) {
                foreach ($data['lines'] as $line) {
                    $consumption = $productionOrder->materialConsumptions()->findOrFail($line['id']);
                    $actual = (float) ($line['actual_quantity'] ?? 0);
                    $planned = (float) $consumption->planned_quantity;

                    $shouldConsume = $line['status'] === 'consumed'
                        || ($actual > 0 && !empty($line['warehouse_id']));

                    if ($shouldConsume && ($actual <= 0 || empty($line['warehouse_id']))) {
                        throw new RuntimeException('برای ثبت مصرف، مقدار واقعی و انبار باید مشخص باشد.');
                    }

                    if (
                        $consumption->inventory_document_id
                        && (
                            ($shouldConsume ? 'consumed' : $line['status']) !== $consumption->status
                            || (int) ($line['warehouse_id'] ?? 0) !== (int) $consumption->warehouse_id
                            || abs($actual - (float) $consumption->actual_quantity) > 0.0001
                            || abs((float) ($line['unit_cost'] ?? $consumption->unit_cost) - (float) $consumption->unit_cost) > 0.01
                        )
                    ) {
                        throw new RuntimeException('برای این ردیف حواله مصرف ثبت شده است؛ تغییر مقدار، انبار یا قیمت را از سند انبار انجام دهید.');
                    }

                    $consumption->update([
                        'warehouse_id' => $line['warehouse_id'] ?? null,
                        'actual_quantity' => $actual,
                        'unit_cost' => $line['unit_cost'] ?? $consumption->unit_cost,
                        'variance_quantity' => $actual - $planned,
                        'status' => $shouldConsume ? 'consumed' : $line['status'],
                    ]);
                }

                $productionOrder->load('materialConsumptions.item', 'project');

                $groups = $productionOrder->materialConsumptions
                    ->filter(fn ($line) => $line->status === 'consumed' && !$line->inventory_document_id && (float) $line->actual_quantity > 0)
                    ->groupBy('warehouse_id');

                foreach ($groups as $warehouseId => $lines) {
                    foreach ($lines->groupBy('item_id') as $itemId => $itemLines) {
                        $required = $itemLines->sum(fn ($line) => (float) $line->actual_quantity);
                        $available = $inventory->availableQuantity((int) $itemId, (int) $warehouseId);

                        if ($available + 0.0001 < $required) {
                            $itemName = $itemLines->first()?->item?->name ?: 'کالا';
                            throw new RuntimeException("موجودی {$itemName} کافی نیست. موجودی فعلی: {$available}، مقدار مصرف: {$required}");
                        }
                    }

                    $document = InventoryDocument::create([
                        'number' => $numbering->next('inventory_consumption', 'IC-'),
                        'type' => 'consumption',
                        'document_date' => now()->toDateString(),
                        'document_time' => now()->format('H:i:s'),
                        'warehouse_id' => (int) $warehouseId,
                        'project_id' => $productionOrder->project_id,
                        'source_type' => ProductionOrder::class,
                        'source_id' => $productionOrder->id,
                        'entry_mode' => 'automatic',
                        'status' => 'confirmed',
                        'description' => 'حواله مصرف سفارش تولید ' . $productionOrder->number,
                        'created_by' => $request->user()?->id,
                        'confirmed_by' => $request->user()?->id,
                        'confirmed_at' => now(),
                    ]);

                    foreach ($lines as $line) {
                        $quantity = (float) $line->actual_quantity;
                        $unitCost = (float) $line->unit_cost;

                        $document->lines()->create([
                            'item_id' => $line->item_id,
                            'quantity' => $quantity,
                            'unit_price' => $unitCost,
                            'line_total' => $quantity * $unitCost,
                            'description' => 'مصرف سفارش تولید ' . $productionOrder->number,
                        ]);

                        $line->update(['inventory_document_id' => $document->id]);
                    }

                    try {
                        $posting->fromInventoryDocument($document, $request->user()?->id);
                    } catch (RuntimeException $exception) {
                        report($exception);
                    }
                }
            });
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['lines' => $exception->getMessage()]);
        }

        return redirect()->route('production-orders.show', $productionOrder)->with('success', 'مصرف مواد به‌روزرسانی شد.');
    }

    public function destroy(ProductionOrder $productionOrder)
    {
        $project = $productionOrder->project;
        $related = $this->relatedDocumentCounts($productionOrder);
        $message = collect($related)
            ->filter(fn ($count) => $count > 0)
            ->map(fn ($count, $title) => "{$title}: {$count}")
            ->implode('، ');

        if ($message !== '') {
            $inventoryNumbers = InventoryDocument::where('source_type', ProductionOrder::class)
                ->where('source_id', $productionOrder->id)
                ->limit(5)
                ->pluck('number')
                ->filter()
                ->implode('، ');
            $details = collect($related)
                ->filter(fn ($count) => $count > 0)
                ->map(fn ($count, $title) => "{$title}: {$count}")
                ->values()
                ->all();

            if ($inventoryNumbers) {
                $details[] = 'نمونه حواله‌های مصرف: ' . $inventoryNumbers;
            }

            return redirect()
                ->route('production-orders.index')
                ->with('error', 'این سفارش تولید سند یا مصرف مرتبط دارد و قابل حذف نیست.')
                ->with('error_details', $details);
        }

        DB::transaction(fn () => $productionOrder->delete());

        $redirect = $project
            ? redirect()->route('projects.show', $project)
            : redirect()->route('production-orders.index');

        return $redirect->with('success', 'سفارش تولید حذف شد.');
    }

    private function relatedDocumentCounts(ProductionOrder $productionOrder): array
    {
        $inventoryDocumentIds = InventoryDocument::where('source_type', ProductionOrder::class)
            ->where('source_id', $productionOrder->id)
            ->pluck('id');

        return [
            'حواله مصرف / سند انبار' => $inventoryDocumentIds->count(),
            'ردیف مصرف دارای حواله' => $productionOrder->materialConsumptions()
                ->whereNotNull('inventory_document_id')
                ->count(),
            'سند حسابداری وابسته' => AccountingDocument::withTrashed()
                ->where('source_type', InventoryDocument::class)
                ->whereIn('source_id', $inventoryDocumentIds)
                ->count(),
        ];
    }

    private function formData(ProductionOrder $order): array
    {
        return [
            'order' => $order,
            'projects' => Project::orderBy('name')->get(),
            'products' => Item::where('type', 'product')->where('is_active', true)->orderBy('name')->get(),
            'boms' => BomVersion::with('item')->orderByDesc('id')->get(),
        ];
    }

    private function validated(Request $request, bool $editing = false): array
    {
        $request->merge([
            'planned_start_date' => jalaliToGregorianDate($request->input('planned_start_date')),
            'planned_end_date' => jalaliToGregorianDate($request->input('planned_end_date')),
            'actual_start_date' => jalaliToGregorianDate($request->input('actual_start_date')),
            'actual_end_date' => jalaliToGregorianDate($request->input('actual_end_date')),
        ]);

        return $request->validate([
            'project_id' => 'required|exists:projects,id',
            'item_id' => 'required|exists:items,id',
            'bom_version_id' => 'nullable|exists:bom_versions,id',
            'quantity' => 'required|numeric|min:0.001',
            'planned_start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date',
            'actual_start_date' => 'nullable|date',
            'actual_end_date' => 'nullable|date',
            'status' => 'required|in:draft,approved,in_progress,testing,completed,closed',
            'description' => 'nullable|string',
        ]);
    }
}
