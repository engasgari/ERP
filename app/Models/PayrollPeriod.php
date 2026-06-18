<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    protected $fillable = [
        'year',
        'month',
        'title',
        'starts_at',
        'ends_at',
        'status',
        'calculated_at',
        'approved_at',
        'closed_at',
        'created_by',
        'approved_by',
        'notes',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
        'calculated_at' => 'datetime',
        'approved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function attendanceCalculations(): HasMany
    {
        return $this->hasMany(AttendanceCalculation::class);
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(Salary::class);
    }

    public function monthlyAttendances(): HasMany
    {
        return $this->hasMany(MonthlyAttendance::class);
    }

    public function payrollCalculations(): HasMany
    {
        return $this->hasMany(PayrollCalculation::class);
    }

    public function getPersianTitleAttribute(): string
    {
        return $this->title ?: getPersianMonthName($this->month) . ' ' . $this->year;
    }
}
