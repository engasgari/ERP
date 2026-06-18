<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollCalculationLine extends Model
{
    protected $fillable = ['payroll_calculation_id', 'payroll_item_id', 'code', 'title', 'type', 'hours', 'rate', 'amount', 'meta'];

    protected $casts = ['hours' => 'decimal:2', 'rate' => 'decimal:2', 'amount' => 'decimal:2', 'meta' => 'array'];

    public function calculation(): BelongsTo
    {
        return $this->belongsTo(PayrollCalculation::class, 'payroll_calculation_id');
    }

    public function payrollItem(): BelongsTo
    {
        return $this->belongsTo(PayrollItem::class);
    }
}
