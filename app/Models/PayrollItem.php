<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollItem extends Model
{
    protected $fillable = [
        'code',
        'title',
        'type',
        'calculation_type',
        'default_amount',
        'default_rate',
        'taxable',
        'insurable',
        'is_statutory',
        'is_active',
        'sort_order',
        'source_title',
        'source_url',
        'description',
    ];

    protected $casts = [
        'default_amount' => 'decimal:2',
        'default_rate' => 'decimal:4',
        'taxable' => 'boolean',
        'insurable' => 'boolean',
        'is_statutory' => 'boolean',
        'is_active' => 'boolean',
    ];
}
