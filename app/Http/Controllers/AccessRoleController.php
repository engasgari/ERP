<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Support\PayrollPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AccessRoleController extends Controller
{
    public function index(Request $request)
    {
        return view('access.roles.index');
    }

    public function create()
    {
        return view('access.roles.form', [
            'role' => new Role(),
            'permissions' => $this->permissionsForForm(),
            'selectedPermissions' => [],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|alpha_dash|unique:roles,name',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::create($validated + ['is_system' => false]);
        $role->permissions()->sync($this->withPayrollLegacyAliases($validated['permissions'] ?? []));

        return redirect()->route('access.roles.index')->with('success', 'نقش با موفقیت ساخته شد.');
    }

    public function edit(Role $role)
    {
        return view('access.roles.form', [
            'role' => $role->load('permissions'),
            'permissions' => $this->permissionsForForm(),
            'selectedPermissions' => $role->permissions->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|alpha_dash|unique:roles,name,' . $role->id,
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->update([
            'name' => $role->is_system ? $role->name : $validated['name'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
        ]);

        if ($role->name !== 'admin') {
            $role->permissions()->sync($this->withPayrollLegacyAliases($validated['permissions'] ?? []));
        }

        return redirect()->route('access.roles.index')->with('success', 'نقش با موفقیت ویرایش شد.');
    }

    public function destroy(Role $role)
    {
        abort_if($role->is_system, 403);

        $role->delete();

        return redirect()->route('access.roles.index')->with('success', 'نقش حذف شد.');
    }

    /**
     * @return Collection<string, \Illuminate\Support\Collection<int, Permission>>
     */
    private function permissionsForForm(): Collection
    {
        $hiddenLegacy = [
            PayrollPermissions::SALARIES_VIEW,
            PayrollPermissions::SALARIES_MANAGE,
        ];

        return Permission::query()
            ->whereNotIn('key', $hiddenLegacy)
            ->orderBy('group')
            ->orderBy('title')
            ->get()
            ->groupBy('group');
    }

    /**
     * Keep salaries.* grants in sync with unified payroll.* for employee access scopes.
     *
     * @param  list<int|string>  $permissionIds
     * @return list<int>
     */
    private function withPayrollLegacyAliases(array $permissionIds): array
    {
        $ids = collect($permissionIds)->map(fn ($id) => (int) $id)->filter()->values();

        $keyIds = Permission::query()
            ->whereIn('key', [
                PayrollPermissions::VIEW,
                PayrollPermissions::MANAGE,
                PayrollPermissions::SALARIES_VIEW,
                PayrollPermissions::SALARIES_MANAGE,
            ])
            ->pluck('id', 'key');

        if ($ids->contains((int) ($keyIds[PayrollPermissions::VIEW] ?? 0))) {
            $ids->push((int) ($keyIds[PayrollPermissions::SALARIES_VIEW] ?? 0));
        }
        if ($ids->contains((int) ($keyIds[PayrollPermissions::MANAGE] ?? 0))) {
            $ids->push((int) ($keyIds[PayrollPermissions::SALARIES_MANAGE] ?? 0));
            $ids->push((int) ($keyIds[PayrollPermissions::SALARIES_VIEW] ?? 0));
        }

        return $ids->filter()->unique()->values()->all();
    }
}
