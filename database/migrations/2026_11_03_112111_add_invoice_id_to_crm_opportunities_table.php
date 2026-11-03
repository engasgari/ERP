<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Safe additive sync for servers that ran an older crm_opportunities schema
 * before invoice_id existed. Does not drop or recreate data.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_opportunities')) {
            return;
        }

        if (! Schema::hasColumn('crm_opportunities', 'invoice_id')) {
            Schema::table('crm_opportunities', function (Blueprint $table) {
                $table->foreignId('invoice_id')
                    ->nullable()
                    ->after('lost_at')
                    ->constrained('invoices')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_opportunities')) {
            return;
        }

        if (Schema::hasColumn('crm_opportunities', 'invoice_id')) {
            Schema::table('crm_opportunities', function (Blueprint $table) {
                $table->dropConstrainedForeignId('invoice_id');
            });
        }
    }
};
