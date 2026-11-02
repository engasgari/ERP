<?php

use App\Models\Permission;
use App\Models\Role;
use App\Support\PayrollPermissions;
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

        foreach ([...PayrollPermissions::definitions(), ...PayrollPermissions::legacyDefinitions()] as $definition) {
            Permission::query()->updateOrCreate(
                ['key' => $definition['key']],
                [
                    'title' => $definition['title'],
                    'group' => $definition['group'],
                ]
            );
        }

        // Collapse old "حقوق" group leftovers into unified payroll group.
        Permission::query()
            ->where('group', 'حقوق')
            ->update(['group' => PayrollPermissions::GROUP]);

        if (! Schema::hasTable('roles') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $payrollViewId = Permission::query()->where('key', PayrollPermissions::VIEW)->value('id');
        $payrollManageId = Permission::query()->where('key', PayrollPermissions::MANAGE)->value('id');
        $salariesViewId = Permission::query()->where('key', PayrollPermissions::SALARIES_VIEW)->value('id');
        $salariesManageId = Permission::query()->where('key', PayrollPermissions::SALARIES_MANAGE)->value('id');

        $admin = Role::query()->where('name', 'admin')->first();
        if ($admin) {
            $admin->permissions()->syncWithoutDetaching(
                Permission::query()->whereIn('key', PayrollPermissions::allKeys())->pluck('id')
            );
        }

        if ($salariesViewId && $payrollViewId) {
            $roleIds = DB::table('permission_role')
                ->where('permission_id', $salariesViewId)
                ->pluck('role_id');

            foreach ($roleIds as $roleId) {
                DB::table('permission_role')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $payrollViewId],
                    []
                );
            }
        }

        if ($salariesManageId && $payrollManageId) {
            $roleIds = DB::table('permission_role')
                ->where('permission_id', $salariesManageId)
                ->pluck('role_id');

            $actionIds = Permission::query()
                ->whereIn('key', PayrollPermissions::manageImpliedKeys())
                ->pluck('id')
                ->all();

            foreach ($roleIds as $roleId) {
                DB::table('permission_role')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $payrollManageId],
                    []
                );

                foreach ($actionIds as $actionId) {
                    DB::table('permission_role')->updateOrInsert(
                        ['role_id' => $roleId, 'permission_id' => $actionId],
                        []
                    );
                }
            }
        }

    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        Permission::query()->where('key', PayrollPermissions::SALARIES_VIEW)->update([
            'title' => 'مشاهده حقوق',
            'group' => 'حقوق',
        ]);
        Permission::query()->where('key', PayrollPermissions::SALARIES_MANAGE)->update([
            'title' => 'مدیریت کامل حقوق',
            'group' => 'حقوق',
        ]);
    }
};
