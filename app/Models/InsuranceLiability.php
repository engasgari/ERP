<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InsuranceLiability extends Model
{
    protected $fillable = [
        'insurance_period_id',
        'employee_share',
        'employer_share',
        'unemployment_share',
        'principal_amount',
        'penalty_amount',
        'other_amount',
        'paid_amount',
        'balance_amount',
        'status',
        'synced_at',
    ];

    protected $casts = [
        'employee_share' => 'decimal:2',
        'employer_share' => 'decimal:2',
        'unemployment_share' => 'decimal:2',
        'principal_amount' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'other_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance_amount' => 'decimal:2',
        'synced_at' => 'datetime',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(InsurancePeriod::class, 'insurance_period_id');
    }

    public function paymentLines(): HasMany
    {
        return $this->hasMany(InsurancePaymentLine::class);
    }

    public function totalDue(): float
    {
        return round(
            (float) $this->principal_amount
            + (float) $this->penalty_amount
            + (float) $this->other_amount,
            2
        );
    }

    public function remainingBalance(): float
    {
        return max(0, round($this->totalDue() - (float) $this->paid_amount, 2));
    }
}
