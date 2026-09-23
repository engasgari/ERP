<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('insurance_periods')
            && Schema::hasTable('insurance_liabilities')
            && Schema::hasTable('insurance_payments')
            && Schema::hasTable('insurance_payment_lines')
        ) {
            return;
        }

        Schema::dropIfExists('insurance_payment_lines');
        Schema::dropIfExists('insurance_payments');
        Schema::dropIfExists('insurance_liabilities');
        Schema::dropIfExists('insurance_periods');

        Schema::create('insurance_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->nullable()->unique()->constrained('payroll_periods')->nullOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('title');
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->date('legal_deadline')->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamps();

            $table->unique(['year', 'month']);
            $table->index(['year', 'status']);
        });

        Schema::create('insurance_liabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_period_id')->unique()->constrained('insurance_periods')->cascadeOnDelete();
            $table->decimal('employee_share', 18, 2)->default(0);
            $table->decimal('employer_share', 18, 2)->default(0);
            $table->decimal('unemployment_share', 18, 2)->default(0);
            $table->decimal('principal_amount', 18, 2)->default(0);
            $table->decimal('penalty_amount', 18, 2)->default(0);
            $table->decimal('other_amount', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('balance_amount', 18, 2)->default(0);
            $table->string('status', 20)->default('unpaid');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('insurance_payments', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->date('payment_date');
            $table->string('method', 20)->default('bank');
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->foreignId('cashbox_id')->nullable()->constrained('cashboxes')->nullOnDelete();
            $table->decimal('total_amount', 18, 2);
            $table->decimal('principal_amount', 18, 2)->default(0);
            $table->decimal('penalty_amount', 18, 2)->default(0);
            $table->decimal('other_amount', 18, 2)->default(0);
            $table->string('reference_number')->nullable();
            $table->string('payment_identifier')->nullable();
            $table->string('receipt_number')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('accounting_document_id')->nullable()->constrained('accounting_documents')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('posted');
            $table->timestamps();

            $table->index('payment_date');
            $table->index('status');
        });

        Schema::create('insurance_payment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_payment_id')->constrained('insurance_payments')->cascadeOnDelete();
            $table->foreignId('insurance_period_id')->constrained('insurance_periods')->cascadeOnDelete();
            $table->foreignId('insurance_liability_id')->constrained('insurance_liabilities')->cascadeOnDelete();
            $table->decimal('principal_amount', 18, 2)->default(0);
            $table->decimal('penalty_amount', 18, 2)->default(0);
            $table->decimal('other_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->timestamps();

            $table->index(['insurance_period_id', 'insurance_payment_id'], 'ins_payment_lines_period_payment_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_payment_lines');
        Schema::dropIfExists('insurance_payments');
        Schema::dropIfExists('insurance_liabilities');
        Schema::dropIfExists('insurance_periods');
    }
};
