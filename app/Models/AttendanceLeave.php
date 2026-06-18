<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLeave extends Model
{
    protected $fillable = [
        'employee_id',
        'request_type',
        'leave_date',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'hours',
        'duration_minutes',
        'total_days',
        'type',
        'reason',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'description',
    ];

    protected $casts = [
        'leave_date' => 'date',
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
}
