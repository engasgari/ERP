<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE invoices
            MODIFY document_type ENUM('proforma', 'invoice', 'return')
            NOT NULL DEFAULT 'invoice'
        ");

        DB::statement("
            ALTER TABLE accounting_documents
            MODIFY type ENUM(
                'manual',
                'sale_invoice',
                'purchase_invoice',
                'sale_return',
                'purchase_return',
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
            UPDATE invoices
            SET document_type = 'invoice'
            WHERE document_type = 'return'
        ");

        DB::statement("
            UPDATE accounting_documents
            SET type = CASE
                WHEN type = 'sale_return' THEN 'sale_invoice'
                WHEN type = 'purchase_return' THEN 'purchase_invoice'
                ELSE type
            END
            WHERE type IN ('sale_return', 'purchase_return')
        ");

        DB::statement("
            ALTER TABLE invoices
            MODIFY document_type ENUM('proforma', 'invoice')
            NOT NULL DEFAULT 'invoice'
        ");

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
};
