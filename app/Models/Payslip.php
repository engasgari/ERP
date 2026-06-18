<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    protected $fillable = ['payroll_calculation_id', 'employee_id', 'payroll_period_id', 'number', 'issued_at', 'snapshot', 'status'];

    protected $casts = ['issued_at' => 'datetime', 'snapshot' => 'array'];

    public function calculation(): BelongsTo
    {
        return $this->belongsTo(PayrollCalculation::class, 'payroll_calculation_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }
}
