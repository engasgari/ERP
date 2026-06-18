<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;

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
            'permissions' => Permission::orderBy('group')->orderBy('title')->get()->groupBy('group'),
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
        $role->permissions()->sync($validated['permissions'] ?? []);

        return redirect()->route('access.roles.index')->with('success', 'نقش با موفقیت ساخته شد.');
    }

    public function edit(Role $role)
    {
        return view('access.roles.form', [
            'role' => $role->load('permissions'),
            'permissions' => Permission::orderBy('group')->orderBy('title')->get()->groupBy('group'),
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
            $role->permissions()->sync($validated['permissions'] ?? []);
        }

        return redirect()->route('access.roles.index')->with('success', 'نقش با موفقیت ویرایش شد.');
    }

    public function destroy(Role $role)
    {
        abort_if($role->is_system, 403);

        $role->delete();

        return redirect()->route('access.roles.index')->with('success', 'نقش حذف شد.');
    }
}
