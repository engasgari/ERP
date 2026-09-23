<?php

namespace App\Livewire\Items;

use App\Livewire\Concerns\ResetsPaginationOnFilterChange;
use App\Models\BomLine;
use App\Models\BomVersion;
use App\Models\InventoryDocumentLine;
use App\Models\InvoiceLine;
use App\Models\Item;
use App\Models\MeasurementUnit;
use App\Models\ProductionMaterialConsumption;
use App\Models\Warehouse;
use App\Services\InventoryPostingService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class Index extends Component
{
    use ResetsPaginationOnFilterChange;
    use WithPagination;

    public string $search = '';
    public string $type = '';
    public string $measurement_unit_id = '';
    public string $category = '';
    public string $is_active = '';
    public ?int $showingId = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'type' => ['except' => ''],
        'measurement_unit_id' => ['except' => ''],
        'category' => ['except' => ''],
        'is_active' => ['except' => ''],
    ];

    public function clearFilters(): void
    {
        $this->reset(['search', 'type', 'measurement_unit_id', 'category', 'is_active']);
        $this->resetPage();
    }

    public function show(int $itemId): void
    {
        $this->showingId = $itemId;
    }

    public function closeModal(): void
    {
        $this->showingId = null;
    }

    public function delete(int $itemId): void
    {
        $item = Item::findOrFail($itemId);
        $details = $this->relatedDetails($item);

        if (!empty($details)) {
            session()->flash('error', 'به دلیل وجود سند یا گردش مرتبط، امکان حذف این کالا/خدمت وجود ندارد.');
            session()->flash('error_details', $details);
            return;
        }

        try {
            $item->delete();
            session()->flash('success', 'کالا/خدمت حذف شد.');
        } catch (Throwable) {
            session()->flash('error', 'به دلیل وجود سند یا گردش مرتبط، امکان حذف این کالا/خدمت وجود ندارد.');
            session()->flash('error_details', ['خطای دیتابیس یا وابستگی']);
        }
    }

    public function render()
    {
        $query = Item::with('unit')->latest();

        if ($this->search !== '') {
            $search = trim($this->search);

            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('category', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
            );
        }

        if ($this->type !== '') {
            $query->where('type', $this->type);
        }

        if ($this->measurement_unit_id !== '') {
            $query->where('measurement_unit_id', $this->measurement_unit_id);
        }

        if ($this->category !== '') {
            $query->where('category', $this->category);
        }

        if ($this->is_active !== '') {
            $query->where('is_active', $this->is_active);
        }

        $items = $query->paginate(15);

        $units = MeasurementUnit::where('is_active', true)
            ->orderBy('name')
            ->get();

        $categories = Item::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $showingItem = $this->showingId
            ? Item::with('unit')->find($this->showingId)
            : null;

        $stockByItem = $this->stockTotalsForItems(
            $items->getCollection()->where('type', 'product')->pluck('id')->all()
        );

        return view('livewire.items.index', compact(
            'items',
            'units',
            'categories',
            'showingItem',
            'stockByItem'
        ));
    }

    private function stockTotalsForItems(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        $inventory = app(InventoryPostingService::class);
        $warehouseIds = Warehouse::where('is_active', true)->pluck('id');

        $stockByItem = [];

        foreach ($itemIds as $itemId) {
            $stockByItem[$itemId] = (float) $warehouseIds
                ->sum(fn (int $warehouseId) => $inventory->availableQuantity($itemId, $warehouseId));
        }

        return $stockByItem;
    }

    private function relatedDetails(Item $item): array
    {
        $related = [
            'ردیف فاکتور' => InvoiceLine::where('item_id', $item->id)->count(),
            'ردیف سند انبار' => InventoryDocumentLine::where('item_id', $item->id)->count(),
            'فرمول BOM محصول' => BomVersion::where('item_id', $item->id)->count(),
            'ردیف BOM' => BomLine::where('component_item_id', $item->id)->count(),
            'مصرف تولید' => ProductionMaterialConsumption::where('item_id', $item->id)->count(),
        ];

        $details = collect($related)
            ->filter(fn ($count) => $count > 0)
            ->map(fn ($count, $title) => "{$title}: {$count}")
            ->values()
            ->all();

        $invoiceNumbers = DB::table('invoice_lines as l')
            ->join('invoices as i', 'i.id', '=', 'l.invoice_id')
            ->where('l.item_id', $item->id)
            ->limit(5)
            ->pluck('i.number')
            ->filter()
            ->implode('، ');

        $inventoryNumbers = DB::table('inventory_document_lines as l')
            ->join('inventory_documents as d', 'd.id', '=', 'l.inventory_document_id')
            ->where('l.item_id', $item->id)
            ->limit(5)
            ->pluck('d.number')
            ->filter()
            ->implode('، ');

        if ($invoiceNumbers) {
            $details[] = 'نمونه فاکتورها: ' . $invoiceNumbers;
        }

        if ($inventoryNumbers) {
            $details[] = 'نمونه اسناد انبار: ' . $inventoryNumbers;
        }

        return $details;
    }
}