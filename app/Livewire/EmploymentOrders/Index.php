<?php

namespace App\Livewire\EmploymentOrders;

use App\Livewire\Concerns\ResetsPaginationOnFilterChange;
use App\Models\Employee;
use App\Models\EmploymentOrder;
use App\Models\Job;
use App\Models\OrganizationUnit;
use App\Models\Position;
use App\Models\Project;
use App\Services\EmploymentOrderService;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use ResetsPaginationOnFilterChange;
    use WithPagination;

    public string $search = '';
    public string $status = '';
    public ?int $editingId = null;
    public array $form = [
        'number' => '',
        'employee_id' => '',
        'position_id' => '',
        'job_id' => '',
        'organization_unit_id' => '',
        'cost_center_code' => '',
        'default_project_id' => '',
        'order_type' => 'hire',
        'employment_type' => 'monthly_contract',
        'insurance_status' => 'insured',
        'effective_date' => '',
        'end_date' => '',
        'base_salary' => 0,
        'hourly_rate' => 0,
        'housing_allowance' => 0,
        'food_allowance' => 0,
        'child_allowance' => 0,
        'transportation_allowance' => 0,
        'notes' => '',
    ];

    protected $queryString = ['search' => ['except' => ''], 'status' => ['except' => ''], 'page' => ['except' => 1]];

    public function rules(): array
    {
        return [
            'form.number' => 'required|string|max:50|unique:employment_orders,number,' . $this->editingId,
            'form.employee_id' => 'required|exists:employees,id',
            'form.position_id' => 'nullable|exists:positions,id',
            'form.job_id' => 'nullable|exists:hr_jobs,id',
            'form.organization_unit_id' => 'nullable|exists:organization_units,id',
            'form.cost_center_code' => 'nullable|string|max:100',
            'form.default_project_id' => 'nullable|exists:projects,id',
            'form.order_type' => 'required|string|max:50',
            'form.employment_type' => 'required|string|max:50',
            'form.insurance_status' => 'required|string|max:50',
            'form.effective_date' => 'required|string',
            'form.end_date' => 'nullable|string',
            'form.base_salary' => 'nullable|numeric|min:0',
            'form.hourly_rate' => 'nullable|numeric|min:0',
            'form.housing_allowance' => 'nullable|numeric|min:0',
            'form.food_allowance' => 'nullable|numeric|min:0',
            'form.child_allowance' => 'nullable|numeric|min:0',
            'form.transportation_allowance' => 'nullable|numeric|min:0',
            'form.notes' => 'nullable|string',
        ];
    }

    public function mount(): void
    {
        $this->form['number'] = 'EO-' . now()->format('Ymd-His');
        $this->form['effective_date'] = todayJalaliDate();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status']);
        $this->resetPage();
    }

    public function edit(int $id): void
    {
        $order = EmploymentOrder::findOrFail($id);
        abort_if($order->status === 'approved', 403);
        $this->editingId = $order->id;
        $this->form = $order->only(array_keys($this->form));
        $this->form['effective_date'] = jalaliDateInputValue('', $order->effective_date);
        $this->form['end_date'] = jalaliDateInputValue('', $order->end_date);
        foreach (['employee_id', 'position_id', 'job_id', 'organization_unit_id', 'default_project_id'] as $key) {
            $this->form[$key] = $this->form[$key] ?: '';
        }
    }

    public function cancel(): void
    {
        $this->editingId = null;
        $this->form = [
            'number' => 'EO-' . now()->format('Ymd-His'),
            'employee_id' => '',
            'position_id' => '',
            'job_id' => '',
            'organization_unit_id' => '',
            'cost_center_code' => '',
            'default_project_id' => '',
            'order_type' => 'hire',
            'employment_type' => 'monthly_contract',
            'insurance_status' => 'insured',
            'effective_date' => todayJalaliDate(),
            'end_date' => '',
            'base_salary' => 0,
            'hourly_rate' => 0,
            'housing_allowance' => 0,
            'food_allowance' => 0,
            'child_allowance' => 0,
            'transportation_allowance' => 0,
            'notes' => '',
        ];
    }

    public function save(): void
    {
        $data = $this->validate()['form'];
        $effectiveDate = jalaliToGregorianDate($data['effective_date']);
        $endDate = $data['end_date'] ? jalaliToGregorianDate($data['end_date']) : null;
        if (! $effectiveDate || ($data['end_date'] && ! $endDate)) {
            session()->flash('error', 'تاریخ حکم معتبر نیست.');
            return;
        }
        foreach (['position_id', 'job_id', 'organization_unit_id', 'default_project_id'] as $key) {
            $data[$key] = $data[$key] ?: null;
        }
        $data['effective_date'] = $effectiveDate;
        $data['end_date'] = $endDate;
        $data['created_by'] = auth()->id();
        $data['status'] = 'draft';

        EmploymentOrder::updateOrCreate(['id' => $this->editingId], $data);
        session()->flash('success', 'حکم کارگزینی ذخیره شد.');
        $this->cancel();
    }

    public function approve(int $id, EmploymentOrderService $service): void
    {
        abort_unless(auth()->user()?->hasPermission('employment-orders.approve'), 403);
        $service->approve(EmploymentOrder::findOrFail($id), auth()->id());
        session()->flash('success', 'حکم تایید و قرارداد پیشنهادی ایجاد شد.');
    }

    public function revert(int $id, EmploymentOrderService $service): void
    {
        abort_unless(auth()->user()?->hasPermission('employment-orders.approve'), 403);
        $service->revert(EmploymentOrder::findOrFail($id), auth()->id());
        session()->flash('success', 'حکم از ثبت قطعی برگشت و دوباره قابل ویرایش شد.');
    }

    public function delete(int $id): void
    {
        $order = EmploymentOrder::findOrFail($id);
        abort_if($order->status === 'approved', 403);
        $order->delete();
        session()->flash('success', 'حکم حذف شد.');
    }

    public function render()
    {
        $orders = EmploymentOrder::with(['employee.party', 'position', 'organizationUnit'])
            ->when($this->search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('number', 'like', '%' . $this->search . '%')
                ->orWhereHas('employee.party', fn ($party) => $party->where('name', 'like', '%' . $this->search . '%'))))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->latest()
            ->paginate(12);

        return view('livewire.employment-orders.index', [
            'orders' => $orders,
            'employees' => Employee::with('party')->orderBy('id')->get(),
            'positions' => Position::where('is_active', true)->orderBy('title')->get(),
            'jobs' => Job::where('is_active', true)->orderBy('title')->get(),
            'units' => OrganizationUnit::where('is_active', true)->orderBy('title')->get(),
            'projects' => Project::orderBy('name')->get(),
        ]);
    }
}
