<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkGroup extends Model
{
    protected $fillable = [
        'code',
        'name',
        'work_shift_id',
        'work_calendar_id',
        'payroll_rules',
        'is_active',
        'description',
    ];

    protected $casts = [
        'payroll_rules' => 'array',
        'is_active' => 'boolean',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class, 'work_shift_id');
    }

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(WorkCalendar::class, 'work_calendar_id');
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'work_group_employee')
            ->withPivot(['id', 'start_date', 'end_date'])
            ->withTimestamps();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(WorkGroupEmployee::class);
    }
}
