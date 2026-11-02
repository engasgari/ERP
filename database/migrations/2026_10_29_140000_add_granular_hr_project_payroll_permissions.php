<?php

use App\Models\Permission;
use App\Models\Role;
use App\Support\AttendancePermissions;
use App\Support\EmployeePermissions;
use App\Support\PayrollPermissions;
use App\Support\ProjectPermissions;
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
            ...ProjectPermissions::definitions(),
            ...EmployeePermissions::definitions(),
            ...AttendancePermissions::definitions(),
            ...PayrollPermissions::definitions(),
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

        if (! Schema::hasTable('roles') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $admin = Role::query()->where('name', 'admin')->first();
        if ($admin) {
            $admin->permissions()->syncWithoutDetaching($permissionIds);
        }

        $this->attachImplied(
            ProjectPermissions::MANAGE,
            ProjectPermissions::manageImpliedKeys()
        );
        $this->attachImplied(
            EmployeePermissions::MANAGE,
            EmployeePermissions::manageImpliedKeys()
        );
        $this->attachImplied(
            AttendancePermissions::MANAGE,
            AttendancePermissions::manageImpliedKeys()
        );
        $this->attachImplied(
            PayrollPermissions::MANAGE,
            PayrollPermissions::manageImpliedKeys()
        );
        $this->attachImplied(
            PayrollPermissions::SALARIES_MANAGE,
            PayrollPermissions::manageImpliedKeys()
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $legacy = [
            ProjectPermissions::VIEW,
            ProjectPermissions::MANAGE,
            EmployeePermissions::VIEW,
            EmployeePermissions::MANAGE,
            AttendancePermissions::VIEW,
            AttendancePermissions::MANAGE,
            AttendancePermissions::IMPORT,
            PayrollPermissions::SALARIES_VIEW,
            PayrollPermissions::SALARIES_MANAGE,
            PayrollPermissions::VIEW,
            PayrollPermissions::MANAGE,
            PayrollPermissions::APPROVE,
            PayrollPermissions::POST,
            PayrollPermissions::INSURANCE_VIEW,
            PayrollPermissions::INSURANCE_MANAGE,
            PayrollPermissions::INSURANCE_PAYMENT,
        ];

        $keys = array_values(array_filter(
            [
                ...ProjectPermissions::allKeys(),
                ...EmployeePermissions::allKeys(),
                ...AttendancePermissions::allKeys(),
                ...PayrollPermissions::allKeys(),
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
