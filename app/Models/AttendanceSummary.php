<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceSummary extends Model
{
    protected $fillable = [
        'employee_id',
        'year',
        'month',
        'payroll_period_id',
        'calendar_days',
        'working_days',
        'required_days',
        'required_hours',
        'required_minutes',
        'worked_days',
        'worked_hours',
        'planned_minutes',
        'worked_minutes',
        'break_minutes',
        'overtime_minutes',
        'overtime_hours',
        'holiday_minutes',
        'delay_minutes',
        'early_leave_minutes',
        'absence_minutes',
        'absence_hours',
        'hourly_leave_minutes',
        'leave_hours',
        'daily_leave_days',
        'hourly_mission_minutes',
        'mission_hours',
        'daily_mission_days',
        'net_payable_hours',
        'meta',
        'failure_reason',
        'status',
    ];

    protected $casts = [
        'required_days' => 'decimal:2',
        'required_hours' => 'decimal:2',
        'worked_days' => 'decimal:2',
        'worked_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'absence_hours' => 'decimal:2',
        'leave_hours' => 'decimal:2',
        'mission_hours' => 'decimal:2',
        'daily_leave_days' => 'decimal:2',
        'daily_mission_days' => 'decimal:2',
        'net_payable_hours' => 'decimal:2',
        'meta' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }
}
