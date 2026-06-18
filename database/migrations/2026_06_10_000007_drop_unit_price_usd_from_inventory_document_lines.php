<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventory_document_lines') && Schema::hasColumn('inventory_document_lines', 'unit_price_usd')) {
            Schema::table('inventory_document_lines', function (Blueprint $table) {
                $table->dropColumn('unit_price_usd');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inventory_document_lines') && ! Schema::hasColumn('inventory_document_lines', 'unit_price_usd')) {
            Schema::table('inventory_document_lines', function (Blueprint $table) {
                $table->decimal('unit_price_usd', 15, 2)->nullable()->after('unit_price');
            });
        }
    }
};
