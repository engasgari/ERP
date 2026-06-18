<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('invoice_lines') || Schema::hasColumn('invoice_lines', 'tax_rate')) {
            return;
        }

        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->decimal('tax_rate', 6, 2)->default(0)->after('discount_amount');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('invoice_lines') || !Schema::hasColumn('invoice_lines', 'tax_rate')) {
            return;
        }

        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropColumn('tax_rate');
        });
    }
};
