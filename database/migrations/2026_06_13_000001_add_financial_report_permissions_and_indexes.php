<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->seedPermissions();
        $this->addIndexes();
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')
                ->whereIn('key', ['financial.reports.view', 'financial.reports.export'])
                ->delete();
        }
    }

    private function seedPermissions(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $now = now();
        $permissions = [
            ['key' => 'financial.reports.view', 'title' => 'مشاهده گزارش‌های مالی', 'group' => 'گزارش‌ها'],
            ['key' => 'financial.reports.export', 'title' => 'خروجی گزارش‌های مالی', 'group' => 'گزارش‌ها'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $permission['key']],
                $permission + ['created_at' => $now, 'updated_at' => $now]
            );
        }

        if (Schema::hasTable('roles')) {
            $roleIds = DB::table('roles')
                ->whereIn('name', ['admin', 'accountant', 'report_viewer'])
                ->pluck('id', 'name');

            $permissionIds = DB::table('permissions')
                ->whereIn('key', array_column($permissions, 'key'))
                ->pluck('id', 'key');

            if (Schema::hasTable('permission_role')) {
                foreach (['admin', 'accountant', 'report_viewer'] as $roleName) {
                    foreach ($permissionIds as $permissionId) {
                        if (! empty($roleIds[$roleName])) {
                            DB::table('permission_role')->updateOrInsert([
                                'role_id' => $roleIds[$roleName],
                                'permission_id' => $permissionId,
                            ]);
                        }
                    }
                }
            }
        }
    }

    private function addIndexes(): void
    {
        if (Schema::hasTable('accounting_documents')) {
            Schema::table('accounting_documents', function (Blueprint $table) {
                $this->safeIndex($table, 'accounting_documents_document_date_status_index', ['document_date', 'status']);
                $this->safeIndex($table, 'accounting_documents_fiscal_year_branch_index', ['fiscal_year_id', 'branch_id']);
            });
        }

        if (Schema::hasTable('accounting_document_lines')) {
            Schema::table('accounting_document_lines', function (Blueprint $table) {
                $this->safeIndex($table, 'accounting_document_lines_account_party_project_index', ['chart_account_id', 'party_id', 'project_id']);
                $this->safeIndex($table, 'accounting_document_lines_cost_center_index', ['cost_center']);
            });
        }

        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                $this->safeIndex($table, 'invoices_invoice_date_status_index', ['invoice_date', 'status']);
                $this->safeIndex($table, 'invoices_fiscal_year_party_project_index', ['fiscal_year_id', 'party_id', 'project_id']);
            });
        }

        if (Schema::hasTable('treasury_transactions')) {
            Schema::table('treasury_transactions', function (Blueprint $table) {
                $this->safeIndex($table, 'treasury_transactions_transaction_date_status_index', ['transaction_date', 'status']);
                $this->safeIndex($table, 'treasury_transactions_party_project_index', ['party_id', 'project_id']);
            });
        }
    }

    private function safeIndex(Blueprint $table, string $name, array $columns): void
    {
        if (! Schema::hasIndex($table->getTable(), $name)) {
            $table->index($columns, $name);
        }
    }
};
