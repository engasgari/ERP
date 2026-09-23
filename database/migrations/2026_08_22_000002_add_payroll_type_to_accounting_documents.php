<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE accounting_documents
            MODIFY type ENUM(
                'manual',
                'sale_invoice',
                'purchase_invoice',
                'payment',
                'receipt',
                'inventory',
                'closing',
                'opening',
                'partner_current',
                'payroll'
            ) NOT NULL DEFAULT 'manual'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE accounting_documents
            MODIFY type ENUM(
                'manual',
                'sale_invoice',
                'purchase_invoice',
                'payment',
                'receipt',
                'inventory',
                'closing',
                'opening',
                'partner_current'
            ) NOT NULL DEFAULT 'manual'
        ");
    }
};
