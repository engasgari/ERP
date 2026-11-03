<?php

namespace App\Livewire\Payroll;

use App\Models\BankAccount;
use App\Models\Cashbox;
use App\Models\PayrollCalculation;
use App\Models\PayrollCalculationLine;
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
        abort_unless(auth()->user()?->hasPermission('payroll.approve'), 403);

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
        abort_unless(auth()->user()?->hasPermission('payroll.adjust'), 403);

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
                // String keeps wire:model.blur stable with text inputs (no float morph wipe).
                'amount' => (string) (int) round((float) $line->amount),
                'locked' => in_array($line->code, ['insurance', 'tax', 'employee_insurance', 'employer_insurance', 'salary_tax'], true),
            ])
            ->all();

        $this->newBonusAmount = '0';
        $this->newDeductionAmount = '0';
        $this->newDeductionTitle = 'کسورات متفرقه';
    }

    public function updatedEditLines($value, $key): void
    {
        // When JS/Livewire patches only *.amount, keep sibling keys (locked/title/id).
        if (! is_string($key) || ! str_ends_with($key, '.amount')) {
            return;
        }

        $index = (int) explode('.', $key)[0];
        if (! isset($this->editLines[$index]) || ! is_array($this->editLines[$index])) {
            return;
        }

        if (! array_key_exists('locked', $this->editLines[$index]) && $this->editingId) {
            $this->restoreLockedEditLineAmounts();
            $this->rehydrateEditLineMeta($index);
        }
    }

    private function rehydrateEditLineMeta(int $index): void
    {
        $lineId = (int) ($this->editLines[$index]['id'] ?? 0);
        if ($lineId <= 0 || ! $this->editingId) {
            return;
        }

        $existing = PayrollCalculationLine::query()
            ->where('payroll_calculation_id', $this->editingId)
            ->whereKey($lineId)
            ->first();

        if (! $existing) {
            return;
        }

        $this->editLines[$index]['id'] = $existing->id;
        $this->editLines[$index]['code'] = (string) $existing->code;
        $this->editLines[$index]['title'] = (string) $existing->title;
        $this->editLines[$index]['type'] = (string) $existing->type;
        $this->editLines[$index]['locked'] = in_array($existing->code, ['insurance', 'tax', 'employee_insurance', 'employer_insurance', 'salary_tax'], true);
        if (! array_key_exists('amount', $this->editLines[$index]) || $this->editLines[$index]['amount'] === null || $this->editLines[$index]['amount'] === '') {
            $this->editLines[$index]['amount'] = (string) (int) round((float) $existing->amount);
        }
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
        abort_unless(auth()->user()?->hasPermission('payroll.adjust'), 403);

        if (! $this->editingId) {
            return;
        }

        // Locked rows may arrive empty if the browser omitted disabled inputs on older markup.
        $this->restoreLockedEditLineAmounts();
        $this->normalizeEditMoneyFields();

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
        abort_unless(auth()->user()?->hasPermission('payroll.adjust'), 403);

        $calculation = PayrollCalculation::with(['period', 'employee.party', 'payments'])->findOrFail($calculationId);
        $draftKey = (string) $calculationId;
        $this->normalizePaymentDraftKey($calculationId);

        $draft = $this->paymentDraftFor($calculationId);
        if ($draft !== []) {
            $this->paymentDrafts[$draftKey] = $draft;
        }
        if (isset($this->paymentDrafts[$draftKey])) {
            $normalizedAmount = normalizeMoneyValue($this->paymentDrafts[$draftKey]['amount'] ?? null);
            $this->paymentDrafts[$draftKey]['amount'] = $normalizedAmount === null ? '' : $normalizedAmount;
        }

        $this->validate([
            "paymentDrafts.$draftKey.payment_date" => ['required', 'string', 'max:20'],
            "paymentDrafts.$draftKey.method" => ['required', 'in:cash,bank'],
            "paymentDrafts.$draftKey.amount" => ['nullable', 'numeric', 'min:1'],
            "paymentDrafts.$draftKey.bank_account_id" => ['nullable', 'required_if:paymentDrafts.'.$draftKey.'.method,bank', 'exists:bank_accounts,id'],
            "paymentDrafts.$draftKey.cashbox_id" => ['nullable', 'required_if:paymentDrafts.'.$draftKey.'.method,cash', 'exists:cashboxes,id'],
            "paymentDrafts.$draftKey.reference_number" => ['nullable', 'string', 'max:100'],
            "paymentDrafts.$draftKey.description" => ['nullable', 'string', 'max:500'],
        ], [], [
            "paymentDrafts.$draftKey.payment_date" => 'تاریخ پرداخت',
            "paymentDrafts.$draftKey.method" => 'روش پرداخت',
            "paymentDrafts.$draftKey.bank_account_id" => 'حساب بانکی',
            "paymentDrafts.$draftKey.cashbox_id" => 'صندوق',
            "paymentDrafts.$draftKey.amount" => 'مبلغ',
        ]);

        $draft = $this->paymentDraftFor($calculationId);
        $draftAmount = normalizeMoneyValue($draft['amount'] ?? null);

        try {
            $paymentDate = jalaliToGregorianDate($draft['payment_date'] ?? null);
            if (! $paymentDate) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "paymentDrafts.$draftKey.payment_date" => 'تاریخ پرداخت نامعتبر است.',
                ]);
            }

            $payment = app(NewPayrollEngineService::class)->payWithDetails($calculation, [
                'payment_date' => $paymentDate,
                'amount' => $draftAmount,
                'method' => $draft['method'] ?? 'bank',
                'bank_account_id' => ($draft['bank_account_id'] ?? null) ?: null,
                'cashbox_id' => ($draft['cashbox_id'] ?? null) ?: null,
                'reference_number' => $draft['reference_number'] ?? null,
                'description' => $draft['description'] ?? null,
            ], auth()->id());
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        unset($this->paymentDrafts[$draftKey], $this->paymentDrafts[$calculationId]);
        session()->flash('success', 'پرداخت حقوق ثبت شد. سند حسابداری: ' . ($payment->accountingDocument?->number ?: '-'));
    }

    public function reversePayment(int $calculationId): void
    {
        abort_unless(auth()->user()?->hasPermission('payroll.adjust'), 403);

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

            $draftKey = (string) $calculation->id;
            $existing = $this->paymentDrafts[$draftKey]
                ?? $this->paymentDrafts[$calculation->id]
                ?? null;

            // Only seed once. Re-merging on every render wipes in-progress amount/bank
            // after Livewire updates from date/bank fields (esp. with string/int key drift).
            if (! is_array($existing)) {
                $this->paymentDrafts[$draftKey] = [
                    'payment_date' => todayJalaliDate(),
                    'method' => 'bank',
                    'bank_account_id' => '',
                    'cashbox_id' => '',
                    'amount' => $remaining,
                    'reference_number' => '',
                    'description' => 'پرداخت حقوق',
                ];
            } else {
                $this->paymentDrafts[$draftKey] = $existing;
                if (! array_key_exists('amount', $existing) || $existing['amount'] === null || $existing['amount'] === '') {
                    $this->paymentDrafts[$draftKey]['amount'] = $remaining;
                }
            }
            // Note: PHP treats 12 and "12" as the same array key — never unset one "variant".
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

    /**
     * @return array<string, mixed>
     */
    private function paymentDraftFor(int $calculationId): array
    {
        $draftKey = (string) $calculationId;

        return $this->paymentDrafts[$draftKey]
            ?? $this->paymentDrafts[$calculationId]
            ?? [];
    }

    private function restoreLockedEditLineAmounts(): void
    {
        if (! $this->editingId || $this->editLines === []) {
            return;
        }

        $dbLines = PayrollCalculation::with('lines')->find($this->editingId)?->lines?->keyBy('id');
        if (! $dbLines) {
            return;
        }

        foreach ($this->editLines as $index => $line) {
            if (! is_array($line)) {
                continue;
            }

            $id = isset($line['id']) ? (int) $line['id'] : 0;
            $existing = $id > 0 ? $dbLines->get($id) : null;
            $locked = ! empty($line['locked'])
                || ($existing && in_array((string) $existing->code, ['insurance', 'tax', 'employee_insurance', 'employer_insurance', 'salary_tax'], true));

            if (! $locked || ! $existing) {
                continue;
            }

            $this->editLines[$index]['amount'] = (string) (int) round((float) $existing->amount);
            $this->editLines[$index]['code'] = (string) $existing->code;
            $this->editLines[$index]['title'] = (string) $existing->title;
            $this->editLines[$index]['type'] = (string) $existing->type;
            $this->editLines[$index]['locked'] = true;
        }
    }

    private function normalizeEditMoneyFields(): void
    {
        foreach ($this->editLines as $index => $line) {
            if (! is_array($line)) {
                continue;
            }
            $normalized = normalizeMoneyValue($line['amount'] ?? null);
            $this->editLines[$index]['amount'] = $normalized === null ? '' : (string) $normalized;
        }

        $this->newBonusAmount = (string) (normalizeMoneyValue($this->newBonusAmount) ?? 0);
        $this->newDeductionAmount = (string) (normalizeMoneyValue($this->newDeductionAmount) ?? 0);
    }

    private function normalizePaymentDraftKey(int $calculationId): void
    {
        // PHP stores 12 and "12" as the same array key — do not unset one "variant".
        $draftKey = (string) $calculationId;
        if (! is_array($this->paymentDrafts[$draftKey] ?? null)) {
            return;
        }
    }
}
