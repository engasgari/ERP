<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Hekmatinasser\Verta\Verta;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'salary_id', 'employee_id', 'amount', 'payment_date',
        'payment_method', 'reference_number', 'description'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function salary()
    {
        return $this->belongsTo(Salary::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    // تاریخ پرداخت به شمسی
    public function getPersianPaymentDateAttribute()
    {
        return Verta::instance($this->payment_date)->format('Y/m/d');
    }
}
