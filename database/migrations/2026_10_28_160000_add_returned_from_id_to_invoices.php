<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices') || Schema::hasColumn('invoices', 'returned_from_id')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('returned_from_id')
                ->nullable()
                ->after('converted_from_id')
                ->constrained('invoices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoices') || ! Schema::hasColumn('invoices', 'returned_from_id')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('returned_from_id');
        });
    }
};
