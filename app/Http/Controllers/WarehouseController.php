<?php

namespace App\Http\Controllers;

use App\Models\InventoryDocument;
use App\Models\Invoice;
use App\Models\ProductionMaterialConsumption;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function index(Request $request)
    {
        return view('warehouses.index');
    }

    public function create()
    {
        return view('warehouses.create');
    }

    public function store(Request $request)
    {
            $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        Warehouse::create($validated);

        return redirect()->route('warehouses.index')
            ->with('success', 'انبار با موفقیت ایجاد شد.');
    }

    public function show(Warehouse $warehouse)
    {
        $warehouse->load(['inventoryDocuments.lines.item', 'inventoryDocuments.project']);

        return view('warehouses.show', compact('warehouse'));
    }

    public function edit(Warehouse $warehouse)
    {
        return view('warehouses.edit', compact('warehouse'));
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $warehouse->update($request->safe()->only(['name', 'description']));

        return redirect()->route('warehouses.index')
            ->with('success', 'انبار با موفقیت ویرایش شد.');
    }

    public function destroy(Warehouse $warehouse)
    {
        $related = [
            'سند انبار' => InventoryDocument::where('warehouse_id', $warehouse->id)
                ->orWhere('target_warehouse_id', $warehouse->id)
                ->count(),
            'فاکتور' => Invoice::where('warehouse_id', $warehouse->id)->count(),
            'مصرف تولید' => ProductionMaterialConsumption::where('warehouse_id', $warehouse->id)->count(),
        ];

        $details = collect($related)
            ->filter(fn ($count) => $count > 0)
            ->map(fn ($count, $title) => "{$title}: {$count}")
            ->values()
            ->all();

        $inventoryNumbers = InventoryDocument::where('warehouse_id', $warehouse->id)
            ->orWhere('target_warehouse_id', $warehouse->id)
            ->limit(5)
            ->pluck('number')
            ->filter()
            ->implode('، ');

        $invoiceNumbers = Invoice::where('warehouse_id', $warehouse->id)
            ->limit(5)
            ->pluck('number')
            ->filter()
            ->implode('، ');

        if ($inventoryNumbers) {
            $details[] = 'نمونه اسناد انبار: ' . $inventoryNumbers;
        }

        if ($invoiceNumbers) {
            $details[] = 'نمونه فاکتورها: ' . $invoiceNumbers;
        }

        if (! empty($details)) {
            return redirect()->route('warehouses.index')
                ->with('error', 'این انبار سند یا گردش مرتبط دارد و قابل حذف نیست.')
                ->with('error_details', $details);
        }

        $warehouse->delete();

        return redirect()->route('warehouses.index')
            ->with('success', 'انبار با موفقیت حذف شد.');
    }
}
