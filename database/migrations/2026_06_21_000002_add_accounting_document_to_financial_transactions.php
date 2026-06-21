<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('financial_transactions')) {
            return;
        }

        if (! Schema::hasColumn('financial_transactions', 'accounting_document_id')) {
            Schema::table('financial_transactions', function (Blueprint $table) {
                $table->foreignId('accounting_document_id')
                    ->nullable()
                    ->after('reference_number')
                    ->constrained('accounting_documents')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('financial_transactions')) {
            return;
        }

        if (Schema::hasColumn('financial_transactions', 'accounting_document_id')) {
            Schema::table('financial_transactions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('accounting_document_id');
            });
        }
    }
};
