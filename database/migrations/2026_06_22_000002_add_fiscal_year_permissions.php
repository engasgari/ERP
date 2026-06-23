<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $now = now();
        $permissions = [
            ['key' => 'fiscal.view', 'title' => 'مشاهده سال مالی', 'group' => 'مالی'],
            ['key' => 'fiscal.close', 'title' => 'بستن سال مالی', 'group' => 'مالی'],
            ['key' => 'fiscal.open', 'title' => 'بازکردن سال مالی', 'group' => 'مالی'],
            ['key' => 'fiscal.reopen', 'title' => 'بازگشایی سال مالی', 'group' => 'مالی'],
            ['key' => 'fiscal.manage_sequences', 'title' => 'مدیریت شماره‌گذاری سال مالی', 'group' => 'مالی'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $permission['key']],
                $permission + ['created_at' => $now, 'updated_at' => $now]
            );
        }

        if (! Schema::hasTable('roles') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('name', ['admin', 'accountant', 'accounting_manager'])
            ->pluck('id', 'name');

        $permissionIds = DB::table('permissions')
            ->whereIn('key', array_column($permissions, 'key'))
            ->pluck('id', 'key');

        foreach (['admin', 'accountant', 'accounting_manager'] as $roleName) {
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

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        DB::table('permissions')
            ->whereIn('key', [
                'fiscal.view',
                'fiscal.close',
                'fiscal.open',
                'fiscal.reopen',
                'fiscal.manage_sequences',
            ])
            ->delete();
    }
};
