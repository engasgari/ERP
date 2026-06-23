<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("
            ALTER TABLE accounting_documents
            MODIFY type ENUM('manual', 'sale_invoice', 'purchase_invoice', 'payment', 'receipt', 'inventory', 'closing', 'opening')
            NOT NULL DEFAULT 'manual'
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("
            ALTER TABLE accounting_documents
            MODIFY type ENUM('manual', 'sale_invoice', 'purchase_invoice', 'payment', 'receipt', 'inventory', 'closing')
            NOT NULL DEFAULT 'manual'
        ");
    }
};
