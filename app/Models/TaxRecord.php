<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxRecord extends Model
{
    protected $fillable = ['payroll_calculation_id', 'employee_id', 'payroll_period_id', 'taxable_income', 'exemption_amount', 'tax_amount', 'brackets'];

    protected $casts = ['taxable_income' => 'decimal:2', 'exemption_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'brackets' => 'array'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function period()
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }
}
