<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InsurancePeriod extends Model
{
    protected $fillable = [
        'payroll_period_id',
        'year',
        'month',
        'title',
        'starts_at',
        'ends_at',
        'legal_deadline',
        'status',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
        'legal_deadline' => 'date',
    ];

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function liability(): HasOne
    {
        return $this->hasOne(InsuranceLiability::class);
    }

    public function paymentLines(): HasMany
    {
        return $this->hasMany(InsurancePaymentLine::class);
    }

    public function getPersianTitleAttribute(): string
    {
        return $this->title ?: getPersianMonthName($this->month) . ' ' . $this->year;
    }
}
