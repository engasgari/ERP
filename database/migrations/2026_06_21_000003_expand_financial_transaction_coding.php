<?php

use App\Models\ChartAccount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('financial_transactions')) {
            if (! Schema::hasColumn('financial_transactions', 'chart_account_id')) {
                Schema::table('financial_transactions', function (Blueprint $table) {
                    $table->foreignId('chart_account_id')->nullable()->after('cashbox_id')->constrained('chart_accounts')->nullOnDelete();
                });
            }

            if (! Schema::hasColumn('financial_transactions', 'detail_account_id')) {
                Schema::table('financial_transactions', function (Blueprint $table) {
                    $table->foreignId('detail_account_id')->nullable()->after('chart_account_id')->constrained('chart_accounts')->nullOnDelete();
                });
            }
        }

        $accounts = [
            ['code' => '520101', 'title' => 'اجاره', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5201'],
            ['code' => '520102', 'title' => 'ناهار پرسنل', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5201'],
            ['code' => '520103', 'title' => 'تنخواه', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5201'],
            ['code' => '520104', 'title' => 'پذیرایی', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5201'],
            ['code' => '520105', 'title' => 'خرید لوازم', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5201'],
            ['code' => '520106', 'title' => 'تعمیرات', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5201'],
            ['code' => '520107', 'title' => 'حمل و نقل', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5201'],
            ['code' => '520108', 'title' => 'سایر هزینه های روزمره', 'level' => 'detail', 'nature' => 'debit', 'parent' => '5201'],
            ['code' => '410201', 'title' => 'درآمد متفرقه', 'level' => 'detail', 'nature' => 'credit', 'parent' => '4102'],
            ['code' => '410202', 'title' => 'سایر درآمدهای غیر فاکتوری', 'level' => 'detail', 'nature' => 'credit', 'parent' => '4102'],
        ];

        foreach ($accounts as $account) {
            $parentId = ChartAccount::where('code', $account['parent'])->value('id');

            ChartAccount::updateOrCreate(
                ['code' => $account['code']],
                [
                    'parent_id' => $parentId,
                    'level' => $account['level'],
                    'title' => $account['title'],
                    'nature' => $account['nature'],
                    'is_active' => true,
                    'is_system' => true,
                ]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('financial_transactions')) {
            if (Schema::hasColumn('financial_transactions', 'detail_account_id')) {
                Schema::table('financial_transactions', function (Blueprint $table) {
                    $table->dropConstrainedForeignId('detail_account_id');
                });
            }

            if (Schema::hasColumn('financial_transactions', 'chart_account_id')) {
                Schema::table('financial_transactions', function (Blueprint $table) {
                    $table->dropConstrainedForeignId('chart_account_id');
                });
            }
        }
    }
};
