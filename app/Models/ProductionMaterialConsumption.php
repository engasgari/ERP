<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionMaterialConsumption extends Model
{
    protected $fillable = [
        'production_order_id',
        'inventory_document_id',
        'item_id',
        'warehouse_id',
        'planned_quantity',
        'actual_quantity',
        'unit_cost',
        'variance_quantity',
        'status',
        'notes',
    ];

    protected $casts = [
        'planned_quantity' => 'decimal:3',
        'actual_quantity' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'variance_quantity' => 'decimal:3',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function inventoryDocument(): BelongsTo
    {
        return $this->belongsTo(InventoryDocument::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function getActualCostAttribute(): float
    {
        return (float) $this->actual_quantity * (float) $this->unit_cost;
    }
}
