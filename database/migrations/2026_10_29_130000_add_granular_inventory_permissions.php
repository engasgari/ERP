<?php

use App\Models\Permission;
use App\Models\Role;
use App\Support\InventoryPermissions;
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

        foreach (InventoryPermissions::definitions() as $definition) {
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

        $inventoryManageId = Permission::query()
            ->where('key', InventoryPermissions::MANAGE)
            ->value('id');

        if ($inventoryManageId) {
            $rolesWithInventoryManage = Role::query()
                ->whereHas('permissions', fn ($query) => $query->where('id', $inventoryManageId))
                ->get();

            $actionIds = Permission::query()
                ->whereIn('key', InventoryPermissions::manageImpliedKeys())
                ->pluck('id')
                ->all();

            foreach ($rolesWithInventoryManage as $role) {
                $role->permissions()->syncWithoutDetaching($actionIds);
            }
        }

        $warehouseManageId = Permission::query()
            ->where('key', InventoryPermissions::WAREHOUSE_MANAGE)
            ->value('id');

        if ($warehouseManageId) {
            $rolesWithWarehouseManage = Role::query()
                ->whereHas('permissions', fn ($query) => $query->where('id', $warehouseManageId))
                ->get();

            $warehouseActionIds = Permission::query()
                ->whereIn('key', InventoryPermissions::warehouseManageImpliedKeys())
                ->pluck('id')
                ->all();

            foreach ($rolesWithWarehouseManage as $role) {
                $role->permissions()->syncWithoutDetaching($warehouseActionIds);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $keys = array_values(array_filter(
            InventoryPermissions::allKeys(),
            fn (string $key) => ! in_array($key, [
                InventoryPermissions::VIEW,
                InventoryPermissions::MANAGE,
                InventoryPermissions::WAREHOUSE_VIEW,
                InventoryPermissions::WAREHOUSE_MANAGE,
            ], true)
        ));

        Permission::query()->whereIn('key', $keys)->delete();
    }
};
