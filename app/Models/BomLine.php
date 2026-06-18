<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomLine extends Model
{
    protected $fillable = [
        'bom_version_id',
        'component_item_id',
        'quantity',
        'measurement_unit_id',
        'waste_percentage',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'waste_percentage' => 'decimal:2',
    ];

    public function bomVersion(): BelongsTo
    {
        return $this->belongsTo(BomVersion::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'component_item_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(MeasurementUnit::class, 'measurement_unit_id');
    }

    public function getNetQuantityAttribute(): float
    {
        return (float) $this->quantity * (1 + ((float) $this->waste_percentage / 100));
    }
}
