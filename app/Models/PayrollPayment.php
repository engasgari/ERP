<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollPayment extends Model
{
    protected $fillable = [
        'payroll_calculation_id',
        'employee_id',
        'payment_date',
        'amount',
        'method',
        'reference_number',
        'status',
        'description',
        'accounting_document_id',
    ];

    protected $casts = ['payment_date' => 'date', 'amount' => 'decimal:2'];

    public function calculation(): BelongsTo
    {
        return $this->belongsTo(PayrollCalculation::class, 'payroll_calculation_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function accountingDocument(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class, 'accounting_document_id');
    }
}
