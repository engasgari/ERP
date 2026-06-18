<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'type',
        'amount',
        'transaction_date',
        'description',
        'reference_type',
        'reference_id'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date'
    ];

    // روابط
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    // اسکوپ برای بدهکاری
    public function scopeDebit($query)
    {
        return $query->where('type', 'debit');
    }

    // اسکوپ برای بستانکاری
    public function scopeCredit($query)
    {
        return $query->where('type', 'credit');
    }

    // اسکوپ برای بازه زمانی
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }

    // دسترسی‌های اضافی
    public function getTypeTextAttribute()
    {
        return $this->type == 'debit' ? 'بدهکار' : 'بستانکار';
    }

    public function getTypeColorAttribute()
    {
        return $this->type == 'debit' ? 'text-red-600' : 'text-green-600';
    }
}
