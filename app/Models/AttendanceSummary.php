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
        'required_days',
        'required_hours',
        'required_minutes',
        'worked_days',
        'worked_hours',
        'planned_minutes',
        'worked_minutes',
        'overtime_minutes',
        'holiday_minutes',
        'delay_minutes',
        'early_leave_minutes',
        'absence_minutes',
        'hourly_leave_minutes',
        'daily_leave_days',
        'hourly_mission_minutes',
        'daily_mission_days',
        'meta',
        'failure_reason',
        'status',
    ];

    protected $casts = [
        'required_days' => 'decimal:2',
        'required_hours' => 'decimal:2',
        'worked_days' => 'decimal:2',
        'worked_hours' => 'decimal:2',
        'daily_leave_days' => 'decimal:2',
        'daily_mission_days' => 'decimal:2',
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
