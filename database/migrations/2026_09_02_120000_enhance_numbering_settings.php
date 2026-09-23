<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('numbering_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('numbering_settings', 'label')) {
                $table->string('label')->nullable()->after('document_key');
            }

            if (! Schema::hasColumn('numbering_settings', 'reuse_deleted_numbers')) {
                $table->boolean('reuse_deleted_numbers')->default(false)->after('padding');
            }

            if (! Schema::hasColumn('numbering_settings', 'sort_order')) {
                $table->unsignedSmallInteger('sort_order')->default(0)->after('reuse_deleted_numbers');
            }
        });
    }

    public function down(): void
    {
        Schema::table('numbering_settings', function (Blueprint $table) {
            foreach (['label', 'reuse_deleted_numbers', 'sort_order'] as $column) {
                if (Schema::hasColumn('numbering_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
