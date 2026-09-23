<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Detach legacy salary accounting sources without deleting posted history.
        if (Schema::hasTable('accounting_documents')) {
            DB::table('accounting_documents')
                ->where('source_type', 'App\\Models\\Salary')
                ->update([
                    'source_type' => null,
                    'source_id' => null,
                ]);
        }

        if (Schema::hasTable('payroll_audits')) {
            DB::table('payroll_audits')
                ->where('auditable_type', 'App\\Models\\Salary')
                ->delete();
        }

        Schema::dropIfExists('salary_lines');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('employee_transactions');
        Schema::dropIfExists('salaries');
    }

    public function down(): void
    {
        // Legacy salary tables are intentionally not recreated.
    }
};
