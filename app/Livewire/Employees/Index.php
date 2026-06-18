<?php

namespace App\Livewire\Employees;

use App\Livewire\Concerns\ResetsPaginationOnFilterChange;
use App\Models\Employee;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use ResetsPaginationOnFilterChange;
    use WithPagination;

    public string $employee_id = '';
    public string $name = '';
    public string $national_code = '';
    public string $position = '';
    public string $phone = '';
    public string $status = '';
    public string $start_date = '';

    protected $queryString = [
        'employee_id' => ['except' => ''],
        'name' => ['except' => ''],
        'national_code' => ['except' => ''],
        'position' => ['except' => ''],
        'phone' => ['except' => ''],
        'status' => ['except' => ''],
        'start_date' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function clearFilters(): void
    {
        $this->reset(['employee_id', 'name', 'national_code', 'position', 'phone', 'status', 'start_date']);
        $this->resetPage();
    }

    public function delete(int $employeeId): void
    {
        Employee::findOrFail($employeeId)->delete();
        session()->flash('success', 'پرسنل با موفقیت حذف شد!');
    }

    public function updateField(int $employeeId, string $field, mixed $value): void
    {
        abort_unless(in_array($field, ['first_name', 'last_name', 'national_code', 'position', 'phone', 'is_active'], true), 403);

        $employee = Employee::findOrFail($employeeId);
        $data = match ($field) {
            'first_name' => ['first_name' => trim((string) $value)],
            'last_name' => ['last_name' => trim((string) $value)],
            'national_code' => ['national_code' => trim((string) $value) ?: null],
            'position' => ['position' => trim((string) $value) ?: null],
            'phone' => ['phone' => trim((string) $value) ?: null],
            'is_active' => ['is_active' => (string) $value === '1'],
        };

        if (($data['first_name'] ?? $employee->first_name) === '' || ($data['last_name'] ?? $employee->last_name) === '') {
            session()->flash('error', 'نام و نام خانوادگی پرسنل الزامی است.');
            return;
        }

        if ($employee->party && in_array($field, ['first_name', 'last_name', 'national_code', 'phone'], true)) {
            if ($field === 'phone') {
                $employee->party->update(['mobile' => trim((string) $value) ?: null]);
            } elseif ($field === 'national_code') {
                $employee->party->update(['national_id' => trim((string) $value) ?: null]);
            } elseif (in_array($field, ['first_name', 'last_name'], true)) {
                $firstName = $field === 'first_name' ? trim((string) $value) : $employee->first_name;
                $lastName = $field === 'last_name' ? trim((string) $value) : $employee->last_name;
                $employee->party->update(['name' => trim($firstName . ' ' . $lastName)]);
            }
        }

        $employee->update($data);
        session()->flash('success', 'تغییرات پرسنل ذخیره شد.');
    }

    public function render()
    {
        $query = Employee::with(['party', 'positionRecord', 'organizationUnit']);

        if ($this->employee_id !== '') {
            $query->where('id', $this->employee_id);
        }

        if ($this->name !== '') {
            $name = $this->name;
            $query->where(function ($employeeQuery) use ($name) {
                $employeeQuery->whereHas('party', fn ($party) => $party->where('name', 'like', '%' . $name . '%')
                    ->orWhere('email', 'like', '%' . $name . '%'))
                    ->orWhere('first_name', 'like', '%' . $name . '%')
                    ->orWhere('last_name', 'like', '%' . $name . '%')
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . $name . '%'])
                    ->orWhere('email', 'like', '%' . $name . '%');
            });
        }

        if ($this->national_code !== '') {
            $query->where(fn ($employeeQuery) => $employeeQuery
                ->where('national_code', 'like', '%' . $this->national_code . '%')
                ->orWhereHas('party', fn ($party) => $party->where('national_id', 'like', '%' . $this->national_code . '%')));
        }

        if ($this->position !== '') {
            $query->where(fn ($employeeQuery) => $employeeQuery
                ->where('position', 'like', '%' . $this->position . '%')
                ->orWhereHas('positionRecord', fn ($position) => $position->where('title', 'like', '%' . $this->position . '%'))
                ->orWhereHas('organizationUnit', fn ($unit) => $unit->where('title', 'like', '%' . $this->position . '%')));
        }

        if ($this->phone !== '') {
            $query->where(fn ($employeeQuery) => $employeeQuery
                ->where('phone', 'like', '%' . $this->phone . '%')
                ->orWhereHas('party', fn ($party) => $party->where('mobile', 'like', '%' . $this->phone . '%')->orWhere('phone', 'like', '%' . $this->phone . '%')));
        }

        if ($this->status !== '') {
            match ($this->status) {
                'active' => $query->where('is_active', true)
                    ->where(fn ($employeeQuery) => $employeeQuery->whereNull('end_date')->orWhereDate('end_date', '>=', now())),
                'inactive' => $query->where('is_active', false),
                'ended' => $query->whereNotNull('end_date')->whereDate('end_date', '<', now()),
                default => null,
            };
        }

        if ($this->start_date !== '') {
            $startDate = jalaliToGregorianDate($this->start_date);
            if ($startDate) {
                $query->whereDate('start_date', '>=', $startDate);
            }
        }

        $employees = $query->latest()->paginate(12);
        $dateErrors = [
            'start_date' => $this->start_date !== '' && ! jalaliToGregorianDate($this->start_date) ? 'تاریخ شروع همکاری معتبر نیست.' : null,
        ];

        return view('livewire.employees.index', compact('employees', 'dateErrors'));
    }
}
