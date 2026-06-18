<?php

namespace App\Models;

use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Salary extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'payroll_period_id',
        'year',
        'month',
        'total_hours',
        'hourly_rate',
        'base_salary',
        'overtime_hours',
        'overtime_rate',
        'overtime_salary',
        'bonus',
        'benefits',
        'gross_salary',
        'deduction',
        'insurance_amount',
        'tax_amount',
        'loan_amount',
        'penalty_amount',
        'total_deductions',
        'net_salary',
        'advance_payment',
        'final_salary',
        'status',
        'accounting_document_id',
        'posted_at',
        'locked_at',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'total_hours' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'base_salary' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'overtime_rate' => 'decimal:2',
        'overtime_salary' => 'decimal:2',
        'bonus' => 'decimal:2',
        'benefits' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'deduction' => 'decimal:2',
        'insurance_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'loan_amount' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'advance_payment' => 'decimal:2',
        'final_salary' => 'decimal:2',
        'posted_at' => 'datetime',
        'locked_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalaryLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function accountingDocument(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class);
    }

    public function sourceAccountingDocuments(): MorphMany
    {
        return $this->morphMany(AccountingDocument::class, 'source');
    }

    public function audits(): MorphMany
    {
        return $this->morphMany(PayrollAudit::class, 'auditable');
    }

    public function getMonthNameAttribute(): string
    {
        return getPersianMonthName($this->month);
    }

    public function getPersianDateAttribute(): string
    {
        return $this->monthName . ' ' . $this->year;
    }

    public function getRemainingAmountAttribute(): float
    {
        $paid = $this->payments->sum('amount');

        return (float) $this->final_salary - (float) $paid;
    }

    public function getIsFullyPaidAttribute(): bool
    {
        return $this->remaining_amount <= 0;
    }

    public function getPersianCreatedAtAttribute(): string
    {
        return Verta::instance($this->created_at)->format('Y/m/d H:i');
    }
}
