<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsurancePaymentLine extends Model
{
    protected $fillable = [
        'insurance_payment_id',
        'insurance_period_id',
        'insurance_liability_id',
        'principal_amount',
        'penalty_amount',
        'other_amount',
        'total_amount',
    ];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'other_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(InsurancePayment::class, 'insurance_payment_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(InsurancePeriod::class, 'insurance_period_id');
    }

    public function liability(): BelongsTo
    {
        return $this->belongsTo(InsuranceLiability::class, 'insurance_liability_id');
    }
}
