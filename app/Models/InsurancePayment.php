<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class InsurancePayment extends Model
{
    protected $fillable = [
        'number',
        'payment_date',
        'method',
        'bank_account_id',
        'cashbox_id',
        'total_amount',
        'principal_amount',
        'penalty_amount',
        'other_amount',
        'reference_number',
        'payment_identifier',
        'receipt_number',
        'description',
        'accounting_document_id',
        'created_by',
        'status',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'total_amount' => 'decimal:2',
        'principal_amount' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'other_amount' => 'decimal:2',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(InsurancePaymentLine::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function cashbox(): BelongsTo
    {
        return $this->belongsTo(Cashbox::class);
    }

    public function accountingDocument(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class, 'accounting_document_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function audits(): MorphMany
    {
        return $this->morphMany(PayrollAudit::class, 'auditable');
    }
}
