<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceMission extends Model
{
    protected $fillable = [
        'employee_id',
        'request_type',
        'project_id',
        'cost_center_id',
        'destination',
        'mission_date',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'hours',
        'duration_minutes',
        'total_days',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'description',
    ];

    protected $casts = [
        'mission_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'hours' => 'decimal:2',
        'total_days' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }
}
