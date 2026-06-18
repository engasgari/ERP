<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventory_documents')) {
            Schema::table('inventory_documents', function (Blueprint $table) {
                if (!Schema::hasColumn('inventory_documents', 'document_time')) {
                    $table->time('document_time')->nullable()->after('document_date');
                }

                if (!Schema::hasColumn('inventory_documents', 'entry_mode')) {
                    $table->enum('entry_mode', ['manual', 'automatic'])->default('manual')->after('source_id');
                }

                if (!Schema::hasColumn('inventory_documents', 'confirmed_by')) {
                    $table->foreignId('confirmed_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('inventory_document_lines')) {
            Schema::table('inventory_document_lines', function (Blueprint $table) {
                if (!Schema::hasColumn('inventory_document_lines', 'unit_price_usd')) {
                    $table->decimal('unit_price_usd', 15, 2)->nullable()->after('unit_price');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inventory_document_lines') && Schema::hasColumn('inventory_document_lines', 'unit_price_usd')) {
            Schema::table('inventory_document_lines', fn (Blueprint $table) => $table->dropColumn('unit_price_usd'));
        }

        if (Schema::hasTable('inventory_documents')) {
            Schema::table('inventory_documents', function (Blueprint $table) {
                if (Schema::hasColumn('inventory_documents', 'confirmed_by')) {
                    $table->dropConstrainedForeignId('confirmed_by');
                }

                foreach (['entry_mode', 'document_time'] as $column) {
                    if (Schema::hasColumn('inventory_documents', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
