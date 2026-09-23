<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmploymentOrderLine extends Model
{
    protected $fillable = [
        'employment_order_id',
        'salary_item_id',
        'code',
        'title',
        'type',
        'amount',
        'is_insurable',
        'is_taxable',
        'is_editable',
        'is_removable',
        'sort_order',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_insurable' => 'boolean',
        'is_taxable' => 'boolean',
        'is_editable' => 'boolean',
        'is_removable' => 'boolean',
        'meta' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(EmploymentOrder::class, 'employment_order_id');
    }

    public function salaryItem(): BelongsTo
    {
        return $this->belongsTo(SalaryItem::class);
    }
}
