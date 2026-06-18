<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsuranceRecord extends Model
{
    protected $fillable = ['payroll_calculation_id', 'employee_id', 'payroll_period_id', 'insurance_days', 'insurance_wage', 'employee_share', 'employer_share', 'unemployment_share'];

    protected $casts = ['insurance_wage' => 'decimal:2', 'employee_share' => 'decimal:2', 'employer_share' => 'decimal:2', 'unemployment_share' => 'decimal:2'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function period()
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }
}
