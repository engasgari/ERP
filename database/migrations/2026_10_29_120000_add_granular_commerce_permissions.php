<?php

use App\Models\Permission;
use App\Models\Role;
use App\Support\CommercePermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $permissionIds = [];

        foreach (CommercePermissions::definitions() as $definition) {
            $permission = Permission::query()->updateOrCreate(
                ['key' => $definition['key']],
                [
                    'title' => $definition['title'],
                    'group' => $definition['group'],
                ]
            );
            $permissionIds[] = $permission->id;
        }

        if (! Schema::hasTable('roles') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $admin = Role::query()->where('name', 'admin')->first();
        if ($admin) {
            $admin->permissions()->syncWithoutDetaching($permissionIds);
        }

        // Roles that already had full commerce.manage keep all new action keys.
        $managePermissionId = Permission::query()
            ->where('key', CommercePermissions::MANAGE)
            ->value('id');

        if (! $managePermissionId) {
            return;
        }

        $rolesWithManage = Role::query()
            ->whereHas('permissions', fn ($query) => $query->where('id', $managePermissionId))
            ->get();

        $actionIds = Permission::query()
            ->whereIn('key', CommercePermissions::manageImpliedKeys())
            ->pluck('id')
            ->all();

        foreach ($rolesWithManage as $role) {
            $role->permissions()->syncWithoutDetaching($actionIds);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $keys = array_values(array_filter(
            CommercePermissions::allKeys(),
            fn (string $key) => ! in_array($key, [CommercePermissions::VIEW, CommercePermissions::MANAGE], true)
        ));

        Permission::query()->whereIn('key', $keys)->delete();
    }
};
