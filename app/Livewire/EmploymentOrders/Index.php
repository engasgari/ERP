<?php

namespace App\Livewire\EmploymentOrders;

use App\Core\Base\BaseLivewire;
use App\Livewire\Concerns\ResetsPaginationOnFilterChange;
use App\Models\Employee;
use App\Models\Job;
use App\Models\OrganizationUnit;
use App\Models\Position;
use App\Models\Project;
use App\Repositories\EmploymentOrderRepository;
use App\Repositories\SalaryItemRepository;
use App\Services\EmploymentOrderCalculationService;
use App\Services\EmploymentOrderService;
use App\Support\Hr\IranLaborEmploymentOrderCatalog;
use Livewire\WithPagination;

class Index extends BaseLivewire
{
    use ResetsPaginationOnFilterChange;
    use WithPagination;

    public string $search = '';
    public string $status = '';
    public string $activeTab = 'info';
    public ?int $editingId = null;
    public string $selectedSalaryItemId = '';
    public string $selectedDeductionItemId = '';

    public array $form = [];
    public array $lines = [];

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function mount(
        EmploymentOrderCalculationService $calculations,
    ): void {
        $this->form = $this->blankForm();
        $this->lines = $calculations->defaultDecreeLines();
        $this->activeTab = 'info';
    }

    private function orders(): EmploymentOrderRepository
    {
        return app(EmploymentOrderRepository::class);
    }

    private function salaryItems(): SalaryItemRepository
    {
        return app(SalaryItemRepository::class);
    }

    private function calculations(): EmploymentOrderCalculationService
    {
        return app(EmploymentOrderCalculationService::class);
    }

    private function service(): EmploymentOrderService
    {
        return app(EmploymentOrderService::class);
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status']);
        $this->resetPage();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function edit(int $id): void
    {
        $order = $this->orders()->findWithDetails($id);
        abort_if($order->status === 'approved', 403);

        $this->editingId = $order->id;
        $this->activeTab = 'info';
        $this->form = $this->blankForm();

        foreach (array_keys($this->form) as $key) {
            if (isset($order->{$key})) {
                $this->form[$key] = $order->{$key};
            }
        }

        $this->form['issue_date'] = jalaliDateInputValue('', $order->issue_date ?: $order->effective_date);
        $this->form['effective_date'] = jalaliDateInputValue('', $order->effective_date);
        $this->form['end_date'] = jalaliDateInputValue('', $order->end_date);

        foreach (['employee_id', 'position_id', 'job_id', 'organization_unit_id', 'default_project_id', 'job_group', 'job_rank', 'job_base', 'children_count'] as $key) {
            $this->form[$key] = $this->form[$key] === null ? '' : (string) $this->form[$key];
        }

        $this->form['hourly_rate'] = $this->formatMoneyInput($order->hourly_rate);
        $this->form['daily_wage'] = $this->formatMoneyInput($order->daily_wage);
        $this->form['monthly_work_hours'] = $this->formatMoneyInput($order->monthly_work_hours ?: 220);
        $this->form['daily_work_hours'] = $this->formatMoneyInput($order->daily_work_hours ?: 7.33);

        $this->lines = $order->lines->map(fn ($line) => [
            'id' => $line->id,
            'salary_item_id' => $line->salary_item_id,
            'code' => $line->code,
            'title' => $line->title,
            'type' => $line->type,
            'amount' => $this->formatMoneyInput($line->amount),
            'is_insurable' => (bool) $line->is_insurable,
            'is_taxable' => (bool) $line->is_taxable,
            'is_editable' => (bool) $line->is_editable,
            'is_removable' => (bool) $line->is_removable,
            'sort_order' => (int) $line->sort_order,
        ])->values()->all();

        if ($this->lines === []) {
            $this->lines = $this->calculations()->defaultDecreeLines();
        }
    }

    public function cancel(): void
    {
        $this->resetEditor();
    }

    public function addEarningLine(): void
    {
        $this->addLineFromCatalog((int) $this->selectedSalaryItemId, 'earning');
        $this->selectedSalaryItemId = '';
        $this->activeTab = 'items';
    }

    public function addDeductionLine(): void
    {
        $this->addLineFromCatalog((int) $this->selectedDeductionItemId, 'deduction');
        $this->selectedDeductionItemId = '';
        $this->activeTab = 'deductions';
    }

    public function removeLine(int $index): void
    {
        if (! isset($this->lines[$index])) {
            return;
        }

        if (! ($this->lines[$index]['is_removable'] ?? true)) {
            $this->addError('lines', 'این قلم قابل حذف نیست.');

            return;
        }

        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function save(): void
    {
        $this->validate($this->rules());

        $issueDate = jalaliToGregorianDate($this->form['issue_date'] ?? '');
        $effectiveDate = jalaliToGregorianDate($this->form['effective_date'] ?? '');
        $endDate = filled($this->form['end_date'] ?? null) ? jalaliToGregorianDate($this->form['end_date']) : null;

        if (! $issueDate || ! $effectiveDate || (filled($this->form['end_date'] ?? null) && ! $endDate)) {
            $this->addError('form.effective_date', 'تاریخ صدور یا تاریخ اجرای حکم معتبر نیست.');
            $this->activeTab = 'info';

            return;
        }

        $header = [
            'number' => $this->form['number'],
            'issue_date' => $issueDate,
            'employee_id' => (int) $this->form['employee_id'],
            'position_id' => $this->form['position_id'] ?: null,
            'job_id' => $this->form['job_id'] ?: null,
            'job_group' => $this->form['job_group'] !== '' ? (int) $this->form['job_group'] : null,
            'job_rank' => $this->form['job_rank'] !== '' ? (int) $this->form['job_rank'] : null,
            'job_base' => $this->form['job_base'] !== '' ? (int) $this->form['job_base'] : null,
            'organization_unit_id' => $this->form['organization_unit_id'] ?: null,
            'cost_center_code' => $this->form['cost_center_code'] ?: null,
            'workshop_code' => $this->form['workshop_code'] ?: null,
            'default_project_id' => $this->form['default_project_id'] ?: null,
            'order_type' => $this->form['order_type'],
            'decree_reason' => $this->form['decree_reason'] ?: null,
            'employment_type' => $this->form['employment_type'],
            'insurance_status' => $this->form['insurance_status'],
            'marital_status' => $this->form['marital_status'] ?: null,
            'effective_date' => $effectiveDate,
            'end_date' => $endDate,
            'hourly_rate' => $this->normalizeMoney($this->form['hourly_rate'] ?? 0),
            'daily_wage' => $this->normalizeMoney($this->form['daily_wage'] ?? 0),
            'monthly_work_hours' => $this->normalizeMoney($this->form['monthly_work_hours'] ?? 220) ?: 220,
            'daily_work_hours' => $this->normalizeMoney($this->form['daily_work_hours'] ?? 7.33) ?: 7.33,
            'children_count' => (int) ($this->form['children_count'] ?: 0),
            'notes' => $this->form['notes'] ?: null,
        ];

        try {
            $this->service()->saveDraft($header, $this->lines, $this->editingId, (int) auth()->id());
        } catch (\Throwable $e) {
            report($e);
            $this->addError('form.number', 'ذخیره حکم ناموفق بود: ' . $e->getMessage());

            return;
        }

        session()->flash('success', 'حکم کارگزینی ذخیره شد.');
        $this->resetEditor();
    }

    public function approve(int $id): void
    {
        abort_unless(auth()->user()?->hasPermission('employment-orders.approve'), 403);

        try {
            $this->service()->approve($this->orders()->findWithDetails($id), (int) auth()->id());
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', 'تایید حکم ناموفق بود: ' . $e->getMessage());

            return;
        }

        session()->flash('success', 'حکم تایید و قرارداد پیشنهادی ایجاد شد.');
    }

    public function revert(int $id): void
    {
        abort_unless(auth()->user()?->hasPermission('employment-orders.approve'), 403);

        try {
            $this->service()->revert($this->orders()->findWithDetails($id), (int) auth()->id());
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', 'برگشت از ثبت قطعی ناموفق بود: ' . $e->getMessage());

            return;
        }

        session()->flash('success', 'حکم از ثبت قطعی برگشت.');
    }

    public function delete(int $id): void
    {
        try {
            $this->service()->deleteDraft($this->orders()->findWithDetails($id));
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', $e->getMessage());

            return;
        }

        session()->flash('success', 'حکم حذف شد.');
        if ($this->editingId === $id) {
            $this->resetEditor();
        }
    }

    public function getSummaryProperty(): array
    {
        return $this->calculations()->summarize($this->lines)->toArray();
    }

    public function getHistoryProperty()
    {
        $employeeId = (int) ($this->form['employee_id'] ?? 0);
        if ($employeeId <= 0) {
            return collect();
        }

        return $this->orders()->historyForEmployee($employeeId, $this->editingId);
    }

    public function render()
    {
        $existingCodes = collect($this->lines)->pluck('code')->filter()->all();

        return view('livewire.employment-orders.index', [
            'orders' => $this->orders()->paginate([
                'search' => $this->search,
                'status' => $this->status,
            ]),
            'employees' => Employee::with('party')->orderBy('id')->get(),
            'positions' => Position::where('is_active', true)->orderBy('title')->get(),
            'jobs' => Job::where('is_active', true)->orderBy('title')->get(),
            'units' => OrganizationUnit::where('is_active', true)->orderBy('title')->get(),
            'projects' => Project::orderBy('name')->get(),
            'orderTypes' => IranLaborEmploymentOrderCatalog::orderTypes(),
            'employmentTypes' => IranLaborEmploymentOrderCatalog::employmentTypes(),
            'insuranceStatuses' => IranLaborEmploymentOrderCatalog::insuranceStatuses(),
            'maritalStatuses' => IranLaborEmploymentOrderCatalog::maritalStatuses(),
            'earningItems' => $this->calculations()->availableItemsForAdd($existingCodes, 'earning'),
            'deductionItems' => $this->calculations()->availableItemsForAdd($existingCodes, 'deduction'),
            'summary' => $this->summary,
            'history' => $this->history,
            'benefitLines' => collect($this->lines)->where('type', 'earning')->all(),
            'deductionLines' => collect($this->lines)->where('type', 'deduction')->all(),
        ]);
    }

    protected function rules(): array
    {
        return [
            'form.number' => 'required|string|max:50|unique:employment_orders,number,' . $this->editingId,
            'form.issue_date' => 'required|string',
            'form.employee_id' => 'required|exists:employees,id',
            'form.order_type' => 'required|string|max:50',
            'form.employment_type' => 'required|string|max:50',
            'form.insurance_status' => 'required|string|max:50',
            'form.effective_date' => 'required|string',
            'form.end_date' => 'nullable|string',
            'lines' => 'array|min:1',
            'lines.*.title' => 'required|string|max:255',
            'lines.*.amount' => 'nullable',
            'lines.*.type' => 'required|in:earning,deduction,employer',
        ];
    }

    private function addLineFromCatalog(int $salaryItemId, string $type): void
    {
        if ($salaryItemId <= 0) {
            $this->addError($type === 'deduction' ? 'selectedDeductionItemId' : 'selectedSalaryItemId', 'یک قلم از فهرست انتخاب کنید.');

            return;
        }

        $item = $this->salaryItems()->findActive($salaryItemId);
        if (! $item || $item->type !== $type) {
            $this->addError('lines', 'قلم انتخاب‌شده معتبر نیست.');

            return;
        }

        if (collect($this->lines)->contains(fn ($line) => ($line['code'] ?? null) === $item->code)) {
            $this->addError('lines', 'این قلم قبلاً به حکم اضافه شده است.');

            return;
        }

        $dto = $this->calculations()->lineFromSalaryItem($item);
        $this->lines[] = array_merge($dto->toArray(), [
            'amount' => $this->formatMoneyInput($dto->amount),
        ]);
    }

    private function resetEditor(): void
    {
        $this->editingId = null;
        $this->activeTab = 'info';
        $this->selectedSalaryItemId = '';
        $this->selectedDeductionItemId = '';
        $this->form = $this->blankForm();
        $this->lines = $this->calculations()->defaultDecreeLines();
        $this->resetErrorBag();
    }

    private function blankForm(): array
    {
        return [
            'number' => 'EO-' . now()->format('Ymd-His'),
            'issue_date' => todayJalaliDate(),
            'employee_id' => '',
            'position_id' => '',
            'job_id' => '',
            'job_group' => '',
            'job_rank' => '',
            'job_base' => '',
            'organization_unit_id' => '',
            'cost_center_code' => '',
            'workshop_code' => '',
            'default_project_id' => '',
            'order_type' => 'salary_change',
            'decree_reason' => '',
            'employment_type' => 'monthly_contract',
            'insurance_status' => 'insured',
            'marital_status' => '',
            'effective_date' => todayJalaliDate(),
            'end_date' => '',
            'daily_wage' => '',
            'hourly_rate' => '',
            'monthly_work_hours' => '220',
            'daily_work_hours' => '7.33',
            'children_count' => '0',
            'notes' => '',
        ];
    }

    private function normalizeMoney(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalized = normalizePersianDigits((string) $value);
        $normalized = str_replace([',', '٬', ' ', '‌'], '', (string) $normalized);

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private function formatMoneyInput(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $formatted = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
