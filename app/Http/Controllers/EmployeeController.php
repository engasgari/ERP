<?php
namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = Employee::query();

        if ($request->filled('employee_id')) {
            $query->where('id', $request->employee_id);
        }

        if ($request->filled('name')) {
            $query->where(function ($employeeQuery) use ($request) {
                $employeeQuery->where('first_name', 'like', '%' . $request->name . '%')
                    ->orWhere('last_name', 'like', '%' . $request->name . '%')
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . $request->name . '%'])
                    ->orWhere('email', 'like', '%' . $request->name . '%');
            });
        }

        if ($request->filled('national_code')) {
            $query->where('national_code', 'like', '%' . $request->national_code . '%');
        }

        if ($request->filled('position')) {
            $query->where('position', 'like', '%' . $request->position . '%');
        }

        if ($request->filled('phone')) {
            $query->where('phone', 'like', '%' . $request->phone . '%');
        }

        if ($request->filled('status')) {
            match ($request->status) {
                'active' => $query->where('is_active', true)
                    ->where(function ($employeeQuery) {
                        $employeeQuery->whereNull('end_date')->orWhereDate('end_date', '>=', now());
                    }),
                'inactive' => $query->where('is_active', false),
                'ended' => $query->whereNotNull('end_date')->whereDate('end_date', '<', now()),
                default => null,
            };
        }

        if ($request->filled('start_date')) {
            $startDate = jalaliToGregorianDate($request->start_date);
            if ($startDate) {
                $query->whereDate('start_date', '>=', $startDate);
            }
        }

        $employees = $query->latest()->paginate(12)->withQueryString();

        $dateErrors = [
            'start_date' => $request->filled('start_date') && !jalaliToGregorianDate($request->start_date) ? 'تاریخ شروع همکاری معتبر نیست.' : null,
        ];

        return view('employees.index', compact('employees', 'dateErrors'));
    }

    public function create()
    {
        return view('employees.create');
    }

    public function store(Request $request)
    {
        $request->merge([
            'start_date' => jalaliToGregorianDate($request->input('start_date')),
            'end_date' => jalaliToGregorianDate($request->input('end_date')),
        ]);

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'national_code' => 'nullable|string|unique:employees|size:10',
            'phone' => 'nullable|string|max:15',
            'email' => 'nullable|email',
            'position' => 'required|string|max:255',
            'salary' => 'nullable|numeric|min:0',
            'address' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'notes' => 'nullable|string',
        ]);

        Employee::create($validated);

        return redirect()->route('employees.index')
            ->with('success', 'پرسنل با موفقیت اضافه شد!');
    }

    public function show(Employee $employee)
    {
        return view('employees.show', compact('employee'));
    }

    public function edit(Employee $employee)
    {
        return view('employees.edit', compact('employee'));
    }

    public function update(Request $request, Employee $employee)
    {
        $request->merge([
            'start_date' => jalaliToGregorianDate($request->input('start_date')),
            'end_date' => jalaliToGregorianDate($request->input('end_date')),
        ]);

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'national_code' => 'nullable|string|size:10|unique:employees,national_code,' . $employee->id,
            'phone' => 'nullable|string|max:15',
            'email' => 'nullable|email',
            'position' => 'required|string|max:255',
            'salary' => 'nullable|numeric|min:0',
            'hourly_rate' => 'required|numeric|min:0', // اضافه شده
            'address' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($employee, $validated, $request) {
            $validated['is_active'] = $request->boolean('is_active');
            $employee->update($validated);
        });

        return redirect()->route('employees.index')
            ->with('success', 'اطلاعات پرسنل با موفقیت ویرایش شد!');
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();

        return redirect()->route('employees.index')
            ->with('success', 'پرسنل با موفقیت حذف شد!');
    }
}
