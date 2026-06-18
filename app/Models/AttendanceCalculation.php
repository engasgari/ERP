<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCalculation extends Model
{
    protected $fillable = [
        'payroll_period_id',
        'employee_id',
        'project_id',
        'normal_hours',
        'overtime_hours',
        'delay_hours',
        'early_leave_hours',
        'mission_hours',
        'absence_hours',
        'leave_hours',
        'holiday_hours',
        'night_hours',
        'payable_hours',
        'hourly_rate',
        'labor_cost',
        'status',
        'meta',
    ];

    protected $casts = [
        'normal_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'delay_hours' => 'decimal:2',
        'early_leave_hours' => 'decimal:2',
        'mission_hours' => 'decimal:2',
        'absence_hours' => 'decimal:2',
        'leave_hours' => 'decimal:2',
        'holiday_hours' => 'decimal:2',
        'night_hours' => 'decimal:2',
        'payable_hours' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'labor_cost' => 'decimal:2',
        'meta' => 'array',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
