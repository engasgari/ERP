<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('payroll_payments', 'accounting_document_id')) {
                $table->foreignId('accounting_document_id')
                    ->nullable()
                    ->after('description')
                    ->constrained('accounting_documents')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_payments', function (Blueprint $table): void {
            if (Schema::hasColumn('payroll_payments', 'accounting_document_id')) {
                $table->dropConstrainedForeignId('accounting_document_id');
            }
        });
    }
};
