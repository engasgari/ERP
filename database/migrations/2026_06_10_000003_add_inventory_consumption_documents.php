<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('inventory_documents')) {
            return;
        }

        DB::statement("ALTER TABLE inventory_documents MODIFY type ENUM('receipt', 'issue', 'transfer', 'consumption') NOT NULL");

        if (Schema::hasTable('chart_accounts')) {
            $assetParentId = DB::table('chart_accounts')->where('code', '11')->value('id');
            $expenseParentId = DB::table('chart_accounts')->where('code', '52')->value('id');

            DB::table('chart_accounts')->updateOrInsert(
                ['code' => '1106'],
                [
                    'parent_id' => $assetParentId,
                    'title' => 'کار در جریان ساخت',
                    'level' => 'subsidiary',
                    'nature' => 'debit',
                    'is_active' => true,
                    'is_system' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            DB::table('chart_accounts')->updateOrInsert(
                ['code' => '5201'],
                [
                    'parent_id' => $expenseParentId,
                    'title' => 'هزینه عمومی',
                    'level' => 'subsidiary',
                    'nature' => 'debit',
                    'is_active' => true,
                    'is_system' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('inventory_documents')) {
            return;
        }

        DB::table('inventory_documents')
            ->where('type', 'consumption')
            ->update(['type' => 'issue']);

        DB::statement("ALTER TABLE inventory_documents MODIFY type ENUM('receipt', 'issue', 'transfer') NOT NULL");
    }
};
