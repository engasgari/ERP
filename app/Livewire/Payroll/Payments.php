<?php

namespace App\Livewire\Payroll;

use App\Models\BankAccount;
use App\Models\Cashbox;
use App\Models\PayrollCalculation;
use App\Models\PayrollPeriod;
use App\Services\NewPayrollEngineService;
use App\Services\PayrollAdjustmentService;
use App\Services\PayrollCalculationService;
use Livewire\Component;
use Livewire\WithPagination;

class Payments extends Component
{
    use WithPagination;

    public int $year = 0;

    public int $month = 0;

    public string $search = '';

    public string $statusFilter = '';

    public ?int $editingId = null;

    /** @var array<int, array<string, mixed>> */
    public array $editLines = [];

    public string $newBonusAmount = '0';

    public string $newDeductionAmount = '0';

    public string $newDeductionTitle = 'کسورات متفرقه';

    /** @var array<string, mixed> */
    public array $paymentDrafts = [];

    protected $queryString = [
        'year' => ['except' => 0],
        'month' => ['except' => 0],
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function mount(): void
    {
        $this->year = (int) request()->integer('year', (int) getCurrentPersianYear());
        $this->month = (int) request()->integer('month', (int) getCurrentPersianMonth());
        $this->normalizePeriodFilters();
    }

    public function updated($name): void
    {
        if (in_array($name, ['year', 'month', 'search', 'statusFilter'], true)) {
            $this->resetPage();
            $this->cancelEdit();
        }
    }

    public function approvePeriod(): void
    {
        $period = $this->currentPeriod();
        if (! $period) {
            session()->flash('error', 'برای این ماه دوره‌ای وجود ندارد.');

            return;
        }

        if ($period->status !== 'calculated') {
            session()->flash('error', 'فقط دوره محاسبه‌شده قابل تایید است.');

            return;
        }

        app(PayrollCalculationService::class)->approve($period, auth()->id());
        session()->flash('success', 'دوره حقوق تایید شد. اکنون می‌توانید پرداخت ثبت کنید.');
    }

    public function startEdit(int $calculationId): void
    {
        $calculation = PayrollCalculation::with('lines')->findOrFail($calculationId);

        $this->editingId = $calculation->id;
        $this->editLines = $calculation->lines
            ->sortBy(fn ($line) => ($line->type === 'earning' ? 0 : 1) . '-' . $line->id)
            ->values()
            ->map(fn ($line) => [
                'id' => $line->id,
                'code' => $line->code,
                'title' => $line->title,
                'type' => $line->type,
                'amount' => (float) $line->amount,
                'locked' => in_array($line->code, ['insurance', 'tax', 'employee_insurance', 'employer_insurance', 'salary_tax'], true),
            ])
            ->all();

        $this->newBonusAmount = '0';
        $this->newDeductionAmount = '0';
        $this->newDeductionTitle = 'کسورات متفرقه';
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->editLines = [];
        $this->newBonusAmount = '0';
        $this->newDeductionAmount = '0';
        $this->resetValidation();
    }

    public function saveEdit(): void
    {
        if (! $this->editingId) {
            return;
        }

        $this->validate([
            'editLines' => ['required', 'array', 'min:1'],
            'editLines.*.id' => ['nullable', 'integer'],
            'editLines.*.amount' => ['required', 'numeric', 'min:0'],
            'newBonusAmount' => ['nullable', 'numeric', 'min:0'],
            'newDeductionAmount' => ['nullable', 'numeric', 'min:0'],
            'newDeductionTitle' => ['nullable', 'string', 'max:120'],
        ], [], [
            'editLines.*.amount' => 'مبلغ ردیف',
            'newBonusAmount' => 'پاداش',
            'newDeductionAmount' => 'کسورات دستی',
        ]);

        $lines = $this->editLines;

        if ((float) $this->newBonusAmount > 0) {
            $lines[] = [
                'code' => 'bonus',
                'title' => 'پاداش',
                'type' => 'earning',
                'amount' => (float) $this->newBonusAmount,
            ];
        }

        if ((float) $this->newDeductionAmount > 0) {
            $lines[] = [
                'code' => 'custom_deduction',
                'title' => $this->newDeductionTitle ?: 'کسورات متفرقه',
                'type' => 'deduction',
                'amount' => (float) $this->newDeductionAmount,
            ];
        }

        $calculation = PayrollCalculation::findOrFail($this->editingId);

        try {
            app(PayrollAdjustmentService::class)->update($calculation, $lines, auth()->id());
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->cancelEdit();
        session()->flash('success', 'مبالغ حقوق ذخیره شد و سند حسابداری حقوق به‌روز شد.');
    }

    public function registerPayment(int $calculationId): void
    {
        $calculation = PayrollCalculation::with(['period', 'employee.party', 'payments'])->findOrFail($calculationId);

        $this->validate([
            "paymentDrafts.$calculationId.payment_date" => ['required', 'string', 'max:20'],
            "paymentDrafts.$calculationId.method" => ['required', 'in:cash,bank'],
            "paymentDrafts.$calculationId.amount" => ['nullable', 'numeric', 'min:1'],
            "paymentDrafts.$calculationId.bank_account_id" => ['nullable', 'required_if:paymentDrafts.'.$calculationId.'.method,bank', 'exists:bank_accounts,id'],
            "paymentDrafts.$calculationId.cashbox_id" => ['nullable', 'required_if:paymentDrafts.'.$calculationId.'.method,cash', 'exists:cashboxes,id'],
            "paymentDrafts.$calculationId.reference_number" => ['nullable', 'string', 'max:100'],
            "paymentDrafts.$calculationId.description" => ['nullable', 'string', 'max:500'],
        ], [], [
            "paymentDrafts.$calculationId.payment_date" => 'تاریخ پرداخت',
            "paymentDrafts.$calculationId.method" => 'روش پرداخت',
            "paymentDrafts.$calculationId.bank_account_id" => 'حساب بانکی',
            "paymentDrafts.$calculationId.cashbox_id" => 'صندوق',
            "paymentDrafts.$calculationId.amount" => 'مبلغ',
        ]);

        try {
            $payment = app(NewPayrollEngineService::class)->payWithDetails($calculation, [
                'payment_date' => jalaliToGregorianDate($this->paymentDrafts[$calculationId]['payment_date']) ?: now()->toDateString(),
                'amount' => $this->paymentDrafts[$calculationId]['amount'] ?? null,
                'method' => $this->paymentDrafts[$calculationId]['method'] ?? 'bank',
                'bank_account_id' => $this->paymentDrafts[$calculationId]['bank_account_id'] ?? null,
                'cashbox_id' => $this->paymentDrafts[$calculationId]['cashbox_id'] ?? null,
                'reference_number' => $this->paymentDrafts[$calculationId]['reference_number'] ?? null,
                'description' => $this->paymentDrafts[$calculationId]['description'] ?? null,
            ], auth()->id());
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        unset($this->paymentDrafts[$calculationId]);
        session()->flash('success', 'پرداخت حقوق ثبت شد. سند حسابداری: ' . ($payment->accountingDocument?->number ?: '-'));
    }

    public function reversePayment(int $calculationId): void
    {
        $calculation = PayrollCalculation::with(['period', 'payments'])->findOrFail($calculationId);

        try {
            app(NewPayrollEngineService::class)->reversePayment($calculation, auth()->id());
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        session()->flash('success', 'پرداخت حقوق برگشت داده شد. سند پرداخت عطف شد و می‌توانید دوباره پرداخت ثبت کنید.');
    }

    public function render()
    {
        $period = $this->currentPeriod();

        $calculations = PayrollCalculation::query()
            ->with([
                'employee.party',
                'period',
                'lines',
                'accountingEntry',
                'accountingDocument',
                'payslip',
                'payments.accountingDocument',
                'payments.bankAccount',
                'payments.cashbox',
            ])
            ->withSum('payments', 'amount')
            ->when($period, fn ($query) => $query->where('payroll_period_id', $period->id))
            ->when(! $period, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->search !== '', function ($query): void {
                $search = trim($this->search);
                $query->whereHas('employee', function ($employee) use ($search): void {
                    $employee->where('employee_code', 'like', "%{$search}%")
                        ->orWhere('personnel_code', 'like', "%{$search}%")
                        ->orWhereHas('party', fn ($party) => $party->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('employee_id')
            ->paginate(20);

        foreach ($calculations as $calculation) {
            $remaining = max(0, (float) $calculation->net_payable - (float) ($calculation->payments_sum_amount ?? 0));
            if ($calculation->status === 'failed' || $remaining <= 0) {
                continue;
            }

            $this->paymentDrafts[$calculation->id] = array_merge([
                'payment_date' => todayJalaliDate(),
                'method' => 'bank',
                'bank_account_id' => '',
                'cashbox_id' => '',
                'amount' => $remaining,
                'reference_number' => '',
                'description' => 'پرداخت حقوق',
            ], $this->paymentDrafts[$calculation->id] ?? []);
        }

        $bankAccounts = BankAccount::query()
            ->where('is_active', true)
            ->orderBy('bank_name')
            ->get();

        $cashboxes = Cashbox::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('livewire.payroll.payments', compact('period', 'calculations', 'bankAccounts', 'cashboxes'));
    }

    private function currentPeriod(): ?PayrollPeriod
    {
        $this->normalizePeriodFilters();

        return PayrollPeriod::query()
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->first();
    }

    private function normalizePeriodFilters(): void
    {
        if ($this->year < 1400 || $this->year > 1500) {
            $this->year = (int) getCurrentPersianYear();
        }

        if ($this->month < 1 || $this->month > 12) {
            $this->month = (int) getCurrentPersianMonth();
        }
    }
}
