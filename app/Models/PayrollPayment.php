<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollPayment extends Model
{
    protected $fillable = ['payroll_calculation_id', 'employee_id', 'payment_date', 'amount', 'method', 'reference_number', 'status', 'description'];

    protected $casts = ['payment_date' => 'date', 'amount' => 'decimal:2'];
}
