<?php

namespace App\Services;

use App\Models\EmployeeTransaction;
use App\Models\Payment;
use App\Models\Salary;
use Carbon\Carbon;

class EmployeeTransactionService
{
    public static function createSalaryTransaction(Salary $salary): EmployeeTransaction
    {
        return EmployeeTransaction::create([
            'employee_id' => $salary->employee_id,
            'type' => 'credit',
            'amount' => $salary->net_salary,
            'transaction_date' => Carbon::today()->toDateString(),
            'description' => 'حقوق کارکرد ' . getPersianMonthName($salary->month) . ' ' . $salary->year,
            'reference_type' => 'salary',
            'reference_id' => $salary->id,
        ]);
    }

    public static function createPaymentTransaction(Payment $payment): EmployeeTransaction
    {
        $method = match ($payment->payment_method) {
            'bank' => 'حساب بانکی',
            'card' => 'کارت به کارت',
            default => 'نقدی',
        };

        return EmployeeTransaction::create([
            'employee_id' => $payment->employee_id,
            'type' => 'debit',
            'amount' => $payment->amount,
            'transaction_date' => $payment->payment_date,
            'description' => 'پرداخت حقوق - ' . $method,
            'reference_type' => 'payment',
            'reference_id' => $payment->id,
        ]);
    }

    public static function createAdvanceTransaction(Salary $salary): ?EmployeeTransaction
    {
        if ((float) $salary->advance_payment <= 0) {
            return null;
        }

        return EmployeeTransaction::create([
            'employee_id' => $salary->employee_id,
            'type' => 'debit',
            'amount' => $salary->advance_payment,
            'transaction_date' => Carbon::today()->toDateString(),
            'description' => 'مساعده حقوق ' . getPersianMonthName($salary->month) . ' ' . $salary->year,
            'reference_type' => 'advance',
            'reference_id' => $salary->id,
        ]);
    }

    public static function createBonusTransaction(Salary $salary): ?EmployeeTransaction
    {
        $amount = (float) $salary->bonus + (float) $salary->benefits;

        if ($amount <= 0) {
            return null;
        }

        return EmployeeTransaction::create([
            'employee_id' => $salary->employee_id,
            'type' => 'credit',
            'amount' => $amount,
            'transaction_date' => Carbon::today()->toDateString(),
            'description' => 'مزایا و پاداش ' . getPersianMonthName($salary->month) . ' ' . $salary->year,
            'reference_type' => 'bonus',
            'reference_id' => $salary->id,
        ]);
    }

    public static function createDeductionTransaction(Salary $salary): ?EmployeeTransaction
    {
        $amount = max(0, (float) $salary->total_deductions - (float) $salary->advance_payment);

        if ($amount <= 0) {
            return null;
        }

        return EmployeeTransaction::create([
            'employee_id' => $salary->employee_id,
            'type' => 'debit',
            'amount' => $amount,
            'transaction_date' => Carbon::today()->toDateString(),
            'description' => 'کسورات حقوق ' . getPersianMonthName($salary->month) . ' ' . $salary->year,
            'reference_type' => 'deduction',
            'reference_id' => $salary->id,
        ]);
    }

    public static function calculateBalance($employeeId): float
    {
        $credit = EmployeeTransaction::where('employee_id', $employeeId)
            ->where('type', 'credit')
            ->sum('amount');

        $debit = EmployeeTransaction::where('employee_id', $employeeId)
            ->where('type', 'debit')
            ->sum('amount');

        return (float) $credit - (float) $debit;
    }
}
