<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayrollCalculation extends Model
{
    protected $fillable = [
        'payroll_period_id', 'employee_id', 'personnel_decree_id', 'monthly_attendance_id', 'attendance_summary_id', 'gross_salary', 'total_benefits',
        'insurance_employee', 'insurance_employer', 'tax_amount', 'total_deductions', 'net_payable',
        'status', 'calculated_at', 'approved_at', 'approved_by', 'rejection_reason', 'failure_reason',
    ];

    protected $casts = [
        'gross_salary' => 'decimal:2', 'total_benefits' => 'decimal:2', 'insurance_employee' => 'decimal:2',
        'insurance_employer' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total_deductions' => 'decimal:2',
        'net_payable' => 'decimal:2', 'calculated_at' => 'datetime', 'approved_at' => 'datetime',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(MonthlyAttendance::class, 'monthly_attendance_id');
    }

    public function personnelDecree(): BelongsTo
    {
        return $this->belongsTo(EmploymentOrder::class, 'personnel_decree_id');
    }

    public function attendanceSummary(): BelongsTo
    {
        return $this->belongsTo(AttendanceSummary::class, 'attendance_summary_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollCalculationLine::class);
    }

    public function payslip(): HasOne
    {
        return $this->hasOne(Payslip::class);
    }

    public function accountingEntry(): HasOne
    {
        return $this->hasOne(PayrollAccountingEntry::class, 'payroll_calculation_id');
    }
}
