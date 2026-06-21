<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('financial_transactions')) {
            if (! Schema::hasColumn('financial_transactions', 'bank_account_id')) {
                Schema::table('financial_transactions', function (Blueprint $table) {
                    $table->foreignId('bank_account_id')->nullable()->after('project_id')->constrained('bank_accounts')->nullOnDelete();
                });
            }

            if (! Schema::hasColumn('financial_transactions', 'cashbox_id')) {
                Schema::table('financial_transactions', function (Blueprint $table) {
                    $table->foreignId('cashbox_id')->nullable()->after('bank_account_id')->constrained('cashboxes')->nullOnDelete();
                });
            }

            $driver = DB::connection()->getDriverName();

            if ($driver === 'sqlite') {
                $this->rebuildSqliteTable();
            } else {
                DB::statement('ALTER TABLE financial_transactions MODIFY project_id BIGINT UNSIGNED NULL');
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('financial_transactions')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->rebuildSqliteTable(true);
        } else {
            DB::statement('ALTER TABLE financial_transactions MODIFY project_id BIGINT UNSIGNED NOT NULL');

            if (Schema::hasColumn('financial_transactions', 'bank_account_id')) {
                Schema::table('financial_transactions', function (Blueprint $table) {
                    $table->dropConstrainedForeignId('bank_account_id');
                });
            }

            if (Schema::hasColumn('financial_transactions', 'cashbox_id')) {
                Schema::table('financial_transactions', function (Blueprint $table) {
                    $table->dropConstrainedForeignId('cashbox_id');
                });
            }
        }
    }

    private function rebuildSqliteTable(bool $restoreOriginalSchema = false): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        Schema::rename('financial_transactions', 'financial_transactions_backup');

        Schema::create('financial_transactions', function (Blueprint $table) use ($restoreOriginalSchema) {
            $table->id();

            if ($restoreOriginalSchema) {
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            } else {
                $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
                $table->foreignId('cashbox_id')->nullable()->constrained('cashboxes')->nullOnDelete();
            }

            $table->enum('type', ['income', 'expense']);
            $table->string('category');
            $table->decimal('amount', 15, 2);
            $table->date('transaction_date');
            $table->text('description')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('attachment')->nullable();
            $table->timestamps();
        });

        $columns = ['id', 'project_id', 'type', 'category', 'amount', 'transaction_date', 'description', 'reference_number', 'attachment', 'created_at', 'updated_at'];

        if (! $restoreOriginalSchema) {
            $columns = ['id', 'project_id', 'bank_account_id', 'cashbox_id', 'type', 'category', 'amount', 'transaction_date', 'description', 'reference_number', 'attachment', 'created_at', 'updated_at'];
        }

        DB::statement(sprintf(
            'INSERT INTO financial_transactions (%s) SELECT %s FROM financial_transactions_backup',
            implode(', ', $columns),
            implode(', ', $columns)
        ));

        Schema::drop('financial_transactions_backup');

        DB::statement('PRAGMA foreign_keys = ON');
    }
};
