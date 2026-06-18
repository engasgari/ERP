<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollAccountingEntry extends Model
{
    protected $fillable = [
        'payroll_calculation_id', 'employee_id', 'payroll_period_id', 'entry_number',
        'salary_expense_debit', 'insurance_expense_debit', 'salary_payable_credit',
        'insurance_payable_credit', 'tax_payable_credit', 'status', 'lines',
    ];

    protected $casts = [
        'salary_expense_debit' => 'decimal:2',
        'insurance_expense_debit' => 'decimal:2',
        'salary_payable_credit' => 'decimal:2',
        'insurance_payable_credit' => 'decimal:2',
        'tax_payable_credit' => 'decimal:2',
        'lines' => 'array',
    ];
}
