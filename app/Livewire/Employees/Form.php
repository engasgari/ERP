<?php

namespace App\Livewire\Employees;

use App\Models\Employee;
use App\Models\OrganizationUnit;
use App\Models\Party;
use App\Models\Position;
use App\Models\Project;
use App\Services\NumberingService;
use Livewire\Component;

class Form extends Component
{
    public ?Employee $employee = null;
    public string $mode = 'create';
    public array $form = [
        'party_id' => '',
        'party_name' => '',
        'national_id' => '',
        'mobile' => '',
        'phone' => '',
        'email' => '',
        'address' => '',
        'personnel_code' => '',
        'attendance_card_number' => '',
        'hire_date' => '',
        'termination_date' => '',
        'employment_type' => 'monthly_contract',
        'insurance_number' => '',
        'position_id' => '',
        'organization_unit_id' => '',
        'default_project_id' => '',
        'status' => 'active',
        'notes' => '',
    ];

    public function mount(?Employee $employee = null): void
    {
        if ($employee?->exists) {
            $employee->load('party');
            $this->employee = $employee;
            $this->mode = 'edit';
            $this->form = [
                'party_id' => $employee->party_id ?: '',
                'party_name' => $employee->party?->name ?: $employee->full_name,
                'national_id' => $employee->party?->national_id ?: $employee->getRawOriginal('national_code'),
                'mobile' => $employee->party?->mobile ?: $employee->getRawOriginal('phone'),
                'phone' => $employee->party?->phone ?: '',
                'email' => $employee->party?->email ?: $employee->getRawOriginal('email'),
                'address' => $employee->party?->address ?: $employee->getRawOriginal('address'),
                'personnel_code' => $employee->personnel_code ?: $employee->employee_code,
                'attendance_card_number' => $employee->attendance_card_number ?: '',
                'hire_date' => jalaliDateInputValue('', $employee->hire_date ?: $employee->start_date),
                'termination_date' => jalaliDateInputValue('', $employee->termination_date ?: $employee->end_date),
                'employment_type' => $employee->employment_type ?: 'monthly_contract',
                'insurance_number' => $employee->insurance_number ?: '',
                'position_id' => $employee->position_id ?: '',
                'organization_unit_id' => $employee->organization_unit_id ?: '',
                'default_project_id' => $employee->default_project_id ?: '',
                'status' => $employee->status ?: 'active',
                'notes' => $employee->notes ?: '',
            ];
        } else {
            $this->form['personnel_code'] = 'EMP-' . now()->format('YmdHis');
            $this->form['hire_date'] = todayJalaliDate();
        }
    }

    public function rules(): array
    {
        $employeeId = $this->employee?->id;
        $partyId = $this->form['party_id'] ?: null;

        return [
            'form.party_id' => 'nullable|exists:parties,id',
            'form.party_name' => 'required|string|max:255',
            'form.national_id' => 'nullable|string|max:50',
            'form.mobile' => 'nullable|string|max:50',
            'form.phone' => 'nullable|string|max:50',
            'form.email' => 'nullable|email|max:255',
            'form.address' => 'nullable|string',
            'form.personnel_code' => 'required|string|max:100|unique:employees,personnel_code,' . $employeeId,
            'form.attendance_card_number' => 'nullable|string|max:100|unique:employees,attendance_card_number,' . $employeeId,
            'form.hire_date' => 'required|string',
            'form.termination_date' => 'nullable|string',
            'form.employment_type' => 'required|string|max:100',
            'form.insurance_number' => 'nullable|string|max:100',
            'form.position_id' => 'nullable|exists:positions,id',
            'form.organization_unit_id' => 'nullable|exists:organization_units,id',
            'form.default_project_id' => 'nullable|exists:projects,id',
            'form.status' => 'required|string|max:50',
            'form.notes' => 'nullable|string',
        ];
    }

    public function updatedFormPartyId($partyId): void
    {
        if (! $partyId) {
            return;
        }

        $party = Party::find($partyId);
        if (! $party) {
            return;
        }

        $this->form['party_name'] = $party->name;
        $this->form['national_id'] = $party->national_id ?: '';
        $this->form['mobile'] = $party->mobile ?: '';
        $this->form['phone'] = $party->phone ?: '';
        $this->form['email'] = $party->email ?: '';
        $this->form['address'] = $party->address ?: '';
    }

    public function save()
    {
        $data = $this->validate()['form'];
        $hireDate = jalaliToGregorianDate($data['hire_date']);
        $terminationDate = $data['termination_date'] ? jalaliToGregorianDate($data['termination_date']) : null;
        if (! $hireDate || ($data['termination_date'] && ! $terminationDate)) {
            session()->flash('error', 'تاریخ استخدام یا پایان همکاری معتبر نیست.');
            return;
        }

        $partyValues = [
                'kind' => 'person',
                'name' => $data['party_name'],
                'national_id' => $data['national_id'] ?: null,
                'mobile' => $data['mobile'] ?: null,
                'phone' => $data['phone'] ?: null,
                'email' => $data['email'] ?: null,
                'address' => $data['address'] ?: null,
                'is_active' => $data['status'] === 'active',
                'notes' => $data['notes'] ?: null,
        ];

        if ($data['party_id']) {
            $party = Party::updateOrCreate(['id' => $data['party_id']], $partyValues);
        } else {
            $numbering = app(NumberingService::class);
            $party = Party::create($partyValues + [
                'code' => $numbering->next('party', 'P-'),
                'detail_code' => $numbering->next('party_detail', 'D-'),
            ]);
        }

        $nameParts = explode(' ', trim($data['party_name']), 2);
        $employeeData = [
            'party_id' => $party->id,
            'personnel_code' => $data['personnel_code'],
            'employee_code' => $data['personnel_code'],
            'personnel_number' => $data['personnel_code'],
            'attendance_card_number' => $data['attendance_card_number'] ?: null,
            'hire_date' => $hireDate,
            'termination_date' => $terminationDate,
            'start_date' => $hireDate,
            'end_date' => $terminationDate,
            'employment_type' => $data['employment_type'],
            'insurance_number' => $data['insurance_number'] ?: null,
            'position_id' => $data['position_id'] ?: null,
            'organization_unit_id' => $data['organization_unit_id'] ?: null,
            'default_project_id' => $data['default_project_id'] ?: null,
            'status' => $data['status'],
            'is_active' => $data['status'] === 'active',
            'first_name' => $nameParts[0] ?: $data['party_name'],
            'last_name' => $nameParts[1] ?? '',
            'national_code' => $data['national_id'] ?: null,
            'phone' => $data['mobile'] ?: $data['phone'] ?: null,
            'email' => $data['email'] ?: null,
            'address' => $data['address'] ?: null,
            'position' => Position::find($data['position_id'])?->title ?: 'تعیین نشده',
            'notes' => $data['notes'] ?: null,
        ];

        $employee = Employee::updateOrCreate(['id' => $this->employee?->id], $employeeData);

        return redirect()->route('employees.show', $employee)->with('success', 'پرونده پرسنلی ذخیره شد.');
    }

    public function render()
    {
        return view('livewire.employees.form', [
            'parties' => Party::where('kind', 'person')->orderBy('name')->limit(300)->get(),
            'positions' => Position::where('is_active', true)->orderBy('title')->get(),
            'units' => OrganizationUnit::where('is_active', true)->orderBy('title')->get(),
            'projects' => Project::orderBy('name')->get(),
        ]);
    }
}
