<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        ['key' => 'crm.ai.customer_intelligence.request', 'title' => 'درخواست خلاصه هوشمند مشتری', 'group' => 'CRM'],
        ['key' => 'crm.ai.runs.view', 'title' => 'مشاهده گزارش اجرای هوش مصنوعی', 'group' => 'CRM'],
    ];

    private const ROLE_GRANTS = [
        'admin' => ['crm.ai.customer_intelligence.request', 'crm.ai.runs.view'],
        'crm_manager' => ['crm.ai.customer_intelligence.request', 'crm.ai.runs.view'],
        'commercial_manager' => ['crm.ai.customer_intelligence.request', 'crm.ai.runs.view'],
        'crm_sales' => ['crm.ai.customer_intelligence.request'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $now = now();
        foreach (self::PERMISSIONS as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $permission['key']],
                $permission + ['created_at' => $now, 'updated_at' => $now]
            );
        }

        if (! Schema::hasTable('roles') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $roleIds = DB::table('roles')->whereIn('name', array_keys(self::ROLE_GRANTS))->pluck('id', 'name');
        $permissionIds = DB::table('permissions')
            ->whereIn('key', array_column(self::PERMISSIONS, 'key'))
            ->pluck('id', 'key');

        foreach (self::ROLE_GRANTS as $roleName => $keys) {
            if (empty($roleIds[$roleName])) {
                continue;
            }

            foreach ($keys as $key) {
                if (! empty($permissionIds[$key])) {
                    DB::table('permission_role')->updateOrInsert([
                        'role_id' => $roleIds[$roleName],
                        'permission_id' => $permissionIds[$key],
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        DB::table('permissions')
            ->whereIn('key', array_column(self::PERMISSIONS, 'key'))
            ->delete();
    }
};
