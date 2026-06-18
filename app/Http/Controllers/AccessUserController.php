<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Models\UserEmployeeAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AccessUserController extends Controller
{
    public function index(Request $request)
    {
        return view('access.users.index');
    }

    public function create()
    {
        return view('access.users.form', [
            'user' => new User(),
            'roles' => Role::orderBy('title')->get(),
            'selectedRoles' => [],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'roles' => 'array',
            'roles.*' => 'exists:roles,id',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->roles()->sync($validated['roles'] ?? []);

        return redirect()->route('access.users.index')->with('success', 'کاربر با موفقیت ساخته شد.');
    }

    public function edit(User $user)
    {
        return view('access.users.form', [
            'user' => $user->load('roles'),
            'roles' => Role::orderBy('title')->get(),
            'selectedRoles' => $user->roles->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8',
            'roles' => 'array',
            'roles.*' => 'exists:roles,id',
        ]);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();
        $user->roles()->sync($validated['roles'] ?? []);

        return redirect()->route('access.users.index')->with('success', 'کاربر با موفقیت ویرایش شد.');
    }

    public function employeeAccess(User $user)
    {
        $accesses = $user->employeeAccesses()->get()->keyBy('employee_id');
        $employees = Employee::orderBy('first_name')->orderBy('last_name')->get();

        return view('access.users.employee-access', compact('user', 'accesses', 'employees'));
    }

    public function updateEmployeeAccess(Request $request, User $user)
    {
        $validated = $request->validate([
            'employees' => 'array',
            'employees.*.employee_id' => 'required|exists:employees,id',
            'employees.*.can_view_work_logs' => 'nullable|boolean',
            'employees.*.can_manage_work_logs' => 'nullable|boolean',
            'employees.*.can_view_salaries' => 'nullable|boolean',
            'employees.*.can_manage_salaries' => 'nullable|boolean',
        ]);

        $user->employeeAccesses()->delete();

        foreach ($validated['employees'] ?? [] as $employeeAccess) {
            $flags = [
                'can_view_work_logs' => !empty($employeeAccess['can_view_work_logs']) || !empty($employeeAccess['can_manage_work_logs']),
                'can_manage_work_logs' => !empty($employeeAccess['can_manage_work_logs']),
                'can_view_salaries' => !empty($employeeAccess['can_view_salaries']) || !empty($employeeAccess['can_manage_salaries']),
                'can_manage_salaries' => !empty($employeeAccess['can_manage_salaries']),
            ];

            if (in_array(true, $flags, true)) {
                UserEmployeeAccess::create([
                    'user_id' => $user->id,
                    'employee_id' => $employeeAccess['employee_id'],
                ] + $flags);
            }
        }

        return redirect()->route('access.users.index')->with('success', 'دسترسی کارمندهای کاربر ذخیره شد.');
    }

    public function destroy(User $user)
    {
        abort_if($user->id === auth()->id(), 403);

        $user->roles()->detach();
        $user->employeeAccesses()->delete();
        $user->delete();

        return redirect()->route('access.users.index')->with('success', 'کاربر حذف شد.');
    }
}
