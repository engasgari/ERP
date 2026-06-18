<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyAttendance extends Model
{
    protected $fillable = [
        'payroll_period_id', 'employee_id', 'work_days', 'present_days', 'required_hours',
        'normal_hours', 'overtime_hours', 'delay_hours', 'early_leave_hours', 'absence_hours',
        'leave_hours', 'mission_hours', 'night_hours', 'holiday_hours', 'payable_hours', 'status', 'meta',
    ];

    protected $casts = [
        'required_hours' => 'decimal:2', 'normal_hours' => 'decimal:2', 'overtime_hours' => 'decimal:2',
        'delay_hours' => 'decimal:2', 'early_leave_hours' => 'decimal:2', 'absence_hours' => 'decimal:2',
        'leave_hours' => 'decimal:2', 'mission_hours' => 'decimal:2', 'night_hours' => 'decimal:2',
        'holiday_hours' => 'decimal:2', 'payable_hours' => 'decimal:2', 'meta' => 'array',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
