<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryItem extends Model
{
    protected $fillable = [
        'code',
        'title',
        'type',
        'category',
        'calculation_type',
        'default_amount',
        'default_rate',
        'is_insurable',
        'is_taxable',
        'is_editable',
        'is_removable',
        'appears_on_decree',
        'is_active',
        'sort_order',
        'legacy_order_field',
        'meta',
    ];

    protected $casts = [
        'default_amount' => 'decimal:2',
        'default_rate' => 'decimal:4',
        'is_insurable' => 'boolean',
        'is_taxable' => 'boolean',
        'is_editable' => 'boolean',
        'is_removable' => 'boolean',
        'appears_on_decree' => 'boolean',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    public function orderLines(): HasMany
    {
        return $this->hasMany(EmploymentOrderLine::class);
    }
}
