<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'project_id',
        'cost_center',
        'work_date',
        'check_in_time',
        'check_out_time',
        'start_time',
        'end_time',
        'hours',
        'overtime_hours',
        'delay_hours',
        'early_leave_hours',
        'mission_hours',
        'leave_hours',
        'absence_hours',
        'description',
        'hourly_rate',
        'total_amount',
        'attendance_source',
        'import_batch',
    ];

    protected $casts = [
        'work_date' => 'date',
        'hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'delay_hours' => 'decimal:2',
        'early_leave_hours' => 'decimal:2',
        'mission_hours' => 'decimal:2',
        'leave_hours' => 'decimal:2',
        'absence_hours' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function calculateHours(): float
    {
        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);

        if ($end->lt($start)) {
            $end->addDay();
        }

        return $end->diffInMinutes($start) / 60;
    }

    public function calculateTotalAmount(): float
    {
        return (float) $this->hours * (float) $this->hourly_rate;
    }

    public function getTimeRangeAttribute(): string
    {
        return $this->start_time . ' - ' . $this->end_time;
    }

    public function getFormattedTotalAmountAttribute(): string
    {
        return number_format((float) $this->total_amount) . ' ریال';
    }

    public function getFormattedHourlyRateAttribute(): string
    {
        return number_format((float) $this->hourly_rate) . ' ریال';
    }
}
