<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('payroll_payments', 'bank_account_id')) {
                $table->foreignId('bank_account_id')
                    ->nullable()
                    ->after('method')
                    ->constrained('bank_accounts')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('payroll_payments', 'cashbox_id')) {
                $table->foreignId('cashbox_id')
                    ->nullable()
                    ->after('bank_account_id')
                    ->constrained('cashboxes')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_payments', function (Blueprint $table): void {
            if (Schema::hasColumn('payroll_payments', 'cashbox_id')) {
                $table->dropConstrainedForeignId('cashbox_id');
            }

            if (Schema::hasColumn('payroll_payments', 'bank_account_id')) {
                $table->dropConstrainedForeignId('bank_account_id');
            }
        });
    }
};
