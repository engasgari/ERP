<?php

use App\Models\Permission;
use App\Models\Role;
use App\Support\HrPermissions;
use App\Support\TreasuryPermissions;
use App\Support\WorklogPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $definitions = [
            ...WorklogPermissions::definitions(),
            ...HrPermissions::definitions(),
            ...TreasuryPermissions::definitions(),
        ];

        $permissionIds = [];

        foreach ($definitions as $definition) {
            $permission = Permission::query()->updateOrCreate(
                ['key' => $definition['key']],
                [
                    'title' => $definition['title'],
                    'group' => $definition['group'],
                ]
            );
            $permissionIds[] = $permission->id;
        }

        // Normalize old treasury group labels into خزانه.
        Permission::query()
            ->where(function ($query) {
                $query->whereIn('key', TreasuryPermissions::allKeys())
                    ->orWhere('key', 'like', 'treasury.%');
            })
            ->update(['group' => TreasuryPermissions::GROUP]);

        if (! Schema::hasTable('roles') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $admin = Role::query()->where('name', 'admin')->first();
        if ($admin) {
            $admin->permissions()->syncWithoutDetaching($permissionIds);
        }

        $this->attachImplied(WorklogPermissions::MANAGE, WorklogPermissions::manageImpliedKeys());
        $this->attachImplied(HrPermissions::MANAGE, HrPermissions::manageImpliedKeys());
        $this->attachImplied(HrPermissions::ORDERS_MANAGE, HrPermissions::ordersManageImpliedKeys());
        $this->attachImplied(HrPermissions::CONTRACTS_MANAGE, HrPermissions::contractsManageImpliedKeys());
        $this->attachImplied(TreasuryPermissions::MANAGE, TreasuryPermissions::manageImpliedKeys());
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $legacy = [
            WorklogPermissions::VIEW,
            WorklogPermissions::MANAGE,
            HrPermissions::VIEW,
            HrPermissions::MANAGE,
            HrPermissions::ORDERS_VIEW,
            HrPermissions::ORDERS_MANAGE,
            HrPermissions::ORDERS_APPROVE,
            HrPermissions::CONTRACTS_VIEW,
            HrPermissions::CONTRACTS_MANAGE,
            TreasuryPermissions::MANAGE,
        ];

        $keys = array_values(array_filter(
            [
                ...WorklogPermissions::allKeys(),
                ...HrPermissions::allKeys(),
                ...TreasuryPermissions::allKeys(),
            ],
            fn (string $key) => ! in_array($key, $legacy, true)
        ));

        Permission::query()->whereIn('key', $keys)->delete();
    }

    /**
     * @param  list<string>  $impliedKeys
     */
    private function attachImplied(string $manageKey, array $impliedKeys): void
    {
        $manageId = Permission::query()->where('key', $manageKey)->value('id');
        if (! $manageId) {
            return;
        }

        $roles = Role::query()
            ->whereHas('permissions', fn ($query) => $query->where('id', $manageId))
            ->get();

        $actionIds = Permission::query()
            ->whereIn('key', $impliedKeys)
            ->pluck('id')
            ->all();

        foreach ($roles as $role) {
            $role->permissions()->syncWithoutDetaching($actionIds);
        }
    }
};
