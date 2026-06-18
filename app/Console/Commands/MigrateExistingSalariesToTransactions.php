<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Salary;
use App\Models\Payment;
use App\Services\EmployeeTransactionService;

class MigrateExistingSalariesToTransactions extends Command
{
    protected $signature = 'transactions:migrate-existing';
    protected $description = 'Migrate existing salaries and payments to transactions';

    public function handle()
    {
        $this->info('شروع انتقال داده‌های موجود...');

        // انتقال حقوق‌ها
        $salaries = Salary::all();
        $bar = $this->output->createProgressBar($salaries->count());

        foreach ($salaries as $salary) {
            EmployeeTransactionService::createSalaryTransaction($salary);

            if ($salary->bonus > 0) {
                EmployeeTransactionService::createBonusTransaction($salary);
            }

            if ($salary->deduction > 0) {
                EmployeeTransactionService::createDeductionTransaction($salary);
            }

            if ($salary->advance_payment > 0) {
                EmployeeTransactionService::createAdvanceTransaction($salary);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->info("\nانتقال حقوق‌ها تکمیل شد.");

        // انتقال پرداخت‌ها
        $payments = Payment::all();
        $bar = $this->output->createProgressBar($payments->count());

        foreach ($payments as $payment) {
            EmployeeTransactionService::createPaymentTransaction($payment);
            $bar->advance();
        }

        $bar->finish();
        $this->info("\nانتقال پرداخت‌ها تکمیل شد.");

        $this->info('انتقال داده‌ها با موفقیت انجام شد.');
        return 0;
    }
}
