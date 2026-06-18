<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkShift extends Model
{
    protected $fillable = [
        'code',
        'name',
        'start_time',
        'end_time',
        'break_minutes',
        'daily_work_hours',
        'overtime_multiplier',
        'late_tolerance_minutes',
        'early_leave_tolerance_minutes',
        'is_active',
        'description',
    ];

    protected $casts = [
        'daily_work_hours' => 'decimal:2',
        'overtime_multiplier' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function workGroups(): HasMany
    {
        return $this->hasMany(WorkGroup::class);
    }
}
