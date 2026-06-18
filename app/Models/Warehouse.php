<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function inventoryDocuments()
    {
        return $this->hasMany(InventoryDocument::class);
    }

    public function getInventoryAttribute(): array
    {
        $rows = DB::table('inventory_document_lines as l')
            ->join('inventory_documents as d', 'd.id', '=', 'l.inventory_document_id')
            ->join('items as i', 'i.id', '=', 'l.item_id')
            ->where('d.status', 'confirmed')
            ->whereIn('d.type', ['receipt', 'issue', 'consumption', 'transfer'])
            ->where(function ($query) {
                $query->where('d.warehouse_id', $this->id)
                    ->orWhere('d.target_warehouse_id', $this->id);
            })
            ->selectRaw("
                i.name as item_name,
                i.category,
                SUM(CASE
                    WHEN d.type = 'receipt' AND d.warehouse_id = ? THEN l.quantity
                    WHEN d.type = 'transfer' AND d.target_warehouse_id = ? THEN l.quantity
                    WHEN d.type IN ('issue', 'consumption') AND d.warehouse_id = ? THEN -l.quantity
                    WHEN d.type = 'transfer' AND d.warehouse_id = ? THEN -l.quantity
                    ELSE 0
                END) as quantity,
                SUM(CASE
                    WHEN d.type = 'receipt' AND d.warehouse_id = ? THEN l.line_total
                    WHEN d.type = 'transfer' AND d.target_warehouse_id = ? THEN l.line_total
                    WHEN d.type IN ('issue', 'consumption') AND d.warehouse_id = ? THEN -l.line_total
                    WHEN d.type = 'transfer' AND d.warehouse_id = ? THEN -l.line_total
                    ELSE 0
                END) as total_value
            ", [$this->id, $this->id, $this->id, $this->id, $this->id, $this->id, $this->id, $this->id])
            ->groupBy('i.name', 'i.category')
            ->get();

        $inventory = [];

        foreach ($rows as $row) {
            $quantity = (float) $row->quantity;
            $totalValue = (float) $row->total_value;

            $inventory[$row->item_name] = [
                'quantity' => $quantity,
                'total_value' => $totalValue,
                'average_price' => abs($quantity) > 0.000001 ? $totalValue / $quantity : 0,
                'category' => $row->category,
            ];
        }

        return $inventory;
    }

    public function getAvailableItemsAttribute(): array
    {
        return collect($this->inventory)
            ->filter(fn ($data) => ($data['quantity'] ?? 0) > 0)
            ->all();
    }

    public function getAvailableItemsWithQuantityAttribute(): array
    {
        return collect($this->inventory)
            ->filter(fn ($data) => ($data['quantity'] ?? 0) > 0)
            ->map(fn ($data) => [
                'quantity' => $data['quantity'],
                'category' => $data['category'] ?? 'عمومی',
            ])
            ->all();
    }

    public function getInventoryCountAttribute(): int
    {
        return count($this->inventory ?? []);
    }
}
