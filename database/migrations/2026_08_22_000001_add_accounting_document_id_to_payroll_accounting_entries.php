<?php

use App\Models\PayrollCalculation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_accounting_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_accounting_entries', 'accounting_document_id')) {
                $table->foreignId('accounting_document_id')
                    ->nullable()
                    ->after('payroll_period_id')
                    ->constrained('accounting_documents')
                    ->nullOnDelete();
            }
        });

        if (Schema::hasTable('payroll_accounting_entries') && Schema::hasTable('accounting_documents')) {
            $entries = DB::table('payroll_accounting_entries')
                ->select('id', 'payroll_calculation_id')
                ->whereNull('accounting_document_id')
                ->get();

            foreach ($entries as $entry) {
                $documentId = DB::table('accounting_documents')
                    ->where('source_type', PayrollCalculation::class)
                    ->where('source_id', $entry->payroll_calculation_id)
                    ->value('id');

                if ($documentId) {
                    DB::table('payroll_accounting_entries')
                        ->where('id', $entry->id)
                        ->update([
                            'accounting_document_id' => $documentId,
                            'status' => 'posted',
                            'updated_at' => now(),
                        ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('payroll_accounting_entries', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_accounting_entries', 'accounting_document_id')) {
                $table->dropConstrainedForeignId('accounting_document_id');
            }
        });
    }
};
