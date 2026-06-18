<?php

namespace App\Livewire\Warehouses;

use App\Livewire\Concerns\ResetsPaginationOnFilterChange;
use App\Models\InventoryDocument;
use App\Models\Invoice;
use App\Models\ProductionMaterialConsumption;
use App\Models\Warehouse;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use ResetsPaginationOnFilterChange;
    use WithPagination;

    public string $name = '';
    public string $status = '';
    public ?int $showingId = null;

    protected $queryString = [
        'name' => ['except' => ''],
        'status' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function clearFilters(): void
    {
        $this->reset(['name', 'status']);
        $this->resetPage();
    }

    public function show(int $warehouseId): void
    {
        $this->showingId = $warehouseId;
    }

    public function closeModal(): void
    {
        $this->showingId = null;
    }

    public function delete(int $warehouseId): void
    {
        $warehouse = Warehouse::findOrFail($warehouseId);
        $details = $this->relatedDetails($warehouse);

        if (! empty($details)) {
            session()->flash('error', 'این انبار سند یا گردش مرتبط دارد و قابل حذف نیست.');
            session()->flash('error_details', $details);
            return;
        }

        $warehouse->delete();
        session()->flash('success', 'انبار با موفقیت حذف شد.');
    }

    public function updateField(int $warehouseId, string $field, mixed $value): void
    {
        abort_unless(in_array($field, ['name', 'description', 'is_active'], true), 403);

        $warehouse = Warehouse::findOrFail($warehouseId);
        $data = match ($field) {
            'name' => ['name' => trim((string) $value)],
            'description' => ['description' => trim((string) $value) ?: null],
            'is_active' => ['is_active' => (string) $value === '1'],
        };

        if (($data['name'] ?? $warehouse->name) === '') {
            session()->flash('error', 'نام انبار الزامی است.');
            return;
        }

        $warehouse->update($data);
        session()->flash('success', 'تغییرات انبار ذخیره شد.');
    }

    public function render()
    {
        $query = Warehouse::query();

        if ($this->name !== '') {
            $name = $this->name;
            $query->where(fn ($warehouseQuery) => $warehouseQuery
                ->where('name', 'like', '%' . $name . '%')
                ->orWhere('description', 'like', '%' . $name . '%'));
        }

        if ($this->status !== '') {
            $query->where('is_active', $this->status === 'active');
        }

        $warehouses = $query->latest()->paginate(12);
        $showingWarehouse = $this->showingId ? Warehouse::find($this->showingId) : null;

        return view('livewire.warehouses.index', compact('warehouses', 'showingWarehouse'));
    }

    private function relatedDetails(Warehouse $warehouse): array
    {
        $related = [
            'سند انبار' => InventoryDocument::where('warehouse_id', $warehouse->id)->orWhere('target_warehouse_id', $warehouse->id)->count(),
            'فاکتور' => Invoice::where('warehouse_id', $warehouse->id)->count(),
            'مصرف تولید' => ProductionMaterialConsumption::where('warehouse_id', $warehouse->id)->count(),
        ];

        $details = collect($related)->filter(fn ($count) => $count > 0)->map(fn ($count, $title) => "{$title}: {$count}")->values()->all();
        $inventoryNumbers = InventoryDocument::where('warehouse_id', $warehouse->id)->orWhere('target_warehouse_id', $warehouse->id)->limit(5)->pluck('number')->filter()->implode('، ');
        $invoiceNumbers = Invoice::where('warehouse_id', $warehouse->id)->limit(5)->pluck('number')->filter()->implode('، ');

        if ($inventoryNumbers) {
            $details[] = 'نمونه اسناد انبار: ' . $inventoryNumbers;
        }
        if ($invoiceNumbers) {
            $details[] = 'نمونه فاکتورها: ' . $invoiceNumbers;
        }

        return $details;
    }
}
