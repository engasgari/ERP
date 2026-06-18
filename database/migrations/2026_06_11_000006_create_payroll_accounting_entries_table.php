<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_accounting_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_calculation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->string('entry_number')->unique();
            $table->decimal('salary_expense_debit', 15, 2)->default(0);
            $table->decimal('insurance_expense_debit', 15, 2)->default(0);
            $table->decimal('salary_payable_credit', 15, 2)->default(0);
            $table->decimal('insurance_payable_credit', 15, 2)->default(0);
            $table->decimal('tax_payable_credit', 15, 2)->default(0);
            $table->string('status')->default('generated');
            $table->json('lines')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_accounting_entries');
    }
};
