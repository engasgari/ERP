<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bank_accounts', 'detail_account_id')) {
            Schema::table('bank_accounts', function (Blueprint $table) {
                $table->foreignId('detail_account_id')->nullable()->after('chart_account_id')->constrained('chart_accounts')->nullOnDelete();
            });
        }

        $parentId = DB::table('chart_accounts')->where('code', '1202')->value('id');

        if (! $parentId) {
            return;
        }

        $banks = DB::table('bank_accounts')->orderBy('id')->get();

        foreach ($banks as $bank) {
            $code = '1202-' . $bank->code;
            $title = trim($bank->bank_name . ' - ' . $bank->code);

            $detailId = DB::table('chart_accounts')->where('code', $code)->value('id');

            if ($detailId) {
                DB::table('chart_accounts')->where('id', $detailId)->update([
                    'parent_id' => $parentId,
                    'level' => 'detail',
                    'title' => $title,
                    'nature' => 'debit',
                    'is_active' => 1,
                    'is_system' => 1,
                    'updated_at' => now(),
                ]);
            } else {
                $detailId = DB::table('chart_accounts')->insertGetId([
                    'parent_id' => $parentId,
                    'level' => 'detail',
                    'code' => $code,
                    'title' => $title,
                    'nature' => 'debit',
                    'is_active' => 1,
                    'is_system' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('bank_accounts')->where('id', $bank->id)->update([
                'detail_account_id' => $detailId,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bank_accounts', 'detail_account_id')) {
            Schema::table('bank_accounts', function (Blueprint $table) {
                $table->dropConstrainedForeignId('detail_account_id');
            });
        }
    }
};
