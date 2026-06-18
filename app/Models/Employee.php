<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_code',
        'party_id',
        'personnel_code',
        'personnel_number',
        'attendance_card_number',
        'hire_date',
        'termination_date',
        'first_name',
        'last_name',
        'national_code',
        'phone',
        'email',
        'department',
        'position',
        'position_id',
        'organization_unit_id',
        'employment_type',
        'salary_type',
        'salary',
        'base_salary',
        'hourly_rate',
        'overtime_rate',
        'default_cost_center',
        'default_project_id',
        'bank_account_number',
        'iban',
        'tax_number',
        'insurance_number',
        'address',
        'start_date',
        'end_date',
        'is_active',
        'status',
        'archived_at',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'hire_date' => 'date',
        'termination_date' => 'date',
        'salary' => 'decimal:2',
        'base_salary' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'overtime_rate' => 'decimal:2',
        'is_active' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public function workLogs(): HasMany
    {
        return $this->hasMany(WorkLog::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function positionRecord(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class);
    }

    public function employmentOrders(): HasMany
    {
        return $this->hasMany(EmploymentOrder::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(EmploymentContract::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(EmployeeHistory::class);
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(Salary::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(EmployeeTransaction::class);
    }

    public function defaultProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'default_project_id');
    }

    public function workGroups(): BelongsToMany
    {
        return $this->belongsToMany(WorkGroup::class, 'work_group_employee')
            ->withPivot(['id', 'start_date', 'end_date'])
            ->withTimestamps();
    }

    public function workGroupAssignments(): HasMany
    {
        return $this->hasMany(WorkGroupEmployee::class);
    }

    public function getFullNameAttribute(): string
    {
        return $this->party?->name ?: trim($this->first_name . ' ' . $this->last_name);
    }

    public function getNationalCodeAttribute($value): ?string
    {
        return $this->party?->national_id ?: $value;
    }

    public function getPhoneAttribute($value): ?string
    {
        return $this->party?->mobile ?: $this->party?->phone ?: $value;
    }

    public function getEmailAttribute($value): ?string
    {
        return $this->party?->email ?: $value;
    }

    public function getAddressAttribute($value): ?string
    {
        return $this->party?->address ?: $value;
    }

    public function getTotalWorkHoursAttribute(): float
    {
        return (float) $this->workLogs()->sum('hours');
    }

    public function getTotalWorkAmountAttribute(): float
    {
        return (float) $this->workLogs()->sum('total_amount');
    }

    public function getAverageDailyHoursAttribute(): float
    {
        $totalDays = $this->workLogs()->selectRaw('DISTINCT work_date')->get()->count();

        return $totalDays > 0 ? $this->total_work_hours / $totalDays : 0;
    }

    public function getCurrentMonthWorkLogsAttribute()
    {
        return $this->workLogs()
            ->whereYear('work_date', now()->year)
            ->whereMonth('work_date', now()->month)
            ->get();
    }

    public function getCurrentMonthSalaryAttribute(): float
    {
        return (float) $this->workLogs()
            ->whereYear('work_date', now()->year)
            ->whereMonth('work_date', now()->month)
            ->sum('total_amount');
    }

    public function getEmploymentDurationAttribute(): string
    {
        if (! $this->start_date) {
            return '-';
        }

        $endDate = $this->end_date ?? now();
        $duration = (int) floor($this->start_date->diffInDays($endDate));

        if ($duration < 30) {
            return $duration . ' روز';
        }

        if ($duration < 365) {
            return floor($duration / 30) . ' ماه';
        }

        $years = floor($duration / 365);
        $months = floor(($duration % 365) / 30);

        return $years . ' سال و ' . $months . ' ماه';
    }

    public function getEmploymentStatusAttribute(): string
    {
        if ($this->status === 'archived' || $this->archived_at) {
            return 'بایگانی شده';
        }

        if ($this->status === 'terminated' || ($this->end_date && now()->gt($this->end_date))) {
            return 'پایان همکاری';
        }

        if (! $this->is_active || $this->status === 'inactive') {
            return 'غیرفعال';
        }

        return 'فعال';
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->employment_status) {
            'فعال' => 'bg-green-100 text-green-800',
            'غیرفعال' => 'bg-gray-100 text-gray-800',
            'پایان همکاری' => 'bg-red-100 text-red-800',
            'بایگانی شده' => 'bg-slate-100 text-slate-800',
            default => 'bg-yellow-100 text-yellow-800',
        };
    }

    public function getIsCurrentlyEmployedAttribute(): bool
    {
        return $this->employment_status === 'فعال';
    }

    public function getFinancialBalanceAttribute(): float
    {
        $debit = $this->transactions()->where('type', 'debit')->sum('amount');
        $credit = $this->transactions()->where('type', 'credit')->sum('amount');

        return (float) $credit - (float) $debit;
    }

    public function getRecentTransactionsAttribute()
    {
        return $this->transactions()->orderBy('transaction_date', 'desc')->take(10)->get();
    }
}
