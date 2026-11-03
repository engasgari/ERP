<?php

namespace App\Livewire\Payroll;

use App\Exceptions\PayrollPrerequisiteException;
use App\Models\AccountingDocument;
use App\Models\BankAccount;
use App\Models\Cashbox;
use App\Models\MonthlyAttendance;
use App\Models\PayrollCalculation;
use App\Models\PayrollCalculationLine;
use App\Models\PayrollPeriod;
use App\Services\NewPayrollEngineService;
use App\Services\PayrollAdjustmentService;
use App\Services\PayrollCalculationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Periods extends Component
{
    public int $year = 0;

    public int $month = 0;

    public int $step = 1;

    public array $paymentDrafts = [];

    public ?int $defaultBankAccountId = null;

    public ?int $defaultCashboxId = null;

    public ?int $paymentModalCalculationId = null;

    public string $paymentModalEmployeeName = '';

    /** Flat Jalali date for the payment modal (avoids nested wire:model sync gaps). */
    public string $paymentModalDate = '';

    public ?int $editingId = null;

    /** @var array<int, array<string, mixed>> */
    public array $editLines = [];

    public string $newBonusAmount = '0';

    public string $newDeductionAmount = '0';

    public string $newDeductionTitle = 'کسورات متفرقه';

    public string $editEmployeeName = '';

    public ?int $viewingDocumentId = null;

    protected $queryString = [
        'year' => ['except' => 0],
        'month' => ['except' => 0],
        'step' => ['except' => 1],
    ];

    public function mount(): void
    {
        $requestedYear = (int) request()->integer('year', 0);
        $requestedMonth = (int) request()->integer('month', 0);
        $requestedStep = (int) request()->integer('step', 0);

        $this->year = $requestedYear > 0 ? $requestedYear : (int) getCurrentPersianYear();
        $this->month = $requestedMonth > 0 ? $requestedMonth : (int) getCurrentPersianMonth();
        $this->normalizePeriodFilters();

        if ($requestedStep >= 1 && $requestedStep <= 3) {
            $this->step = $this->clampStep($requestedStep);
        } else {
            $this->step = $this->suggestedStep($this->currentPeriod());
        }
    }

    public function updated($name): void
    {
        if (in_array($name, ['year', 'month'], true)) {
            $this->normalizePeriodFilters();
            $this->paymentDrafts = [];
            $this->step = $this->suggestedStep($this->currentPeriod());
        }
    }

    public function goToStep(int $step): void
    {
        $this->step = $this->clampStep($step);
    }

    public function ensurePeriod(bool $flash = true): ?PayrollPeriod
    {
        abort_unless(auth()->user()?->hasPermission('payroll.periods.create') || auth()->user()?->hasPermission('payroll.view'), 403);

        $this->normalizePeriodFilters();

        $existing = $this->currentPeriod();
        if (! auth()->user()?->hasPermission('payroll.periods.create')) {
            if (! $existing) {
                session()->flash('error', 'برای این ماه هنوز دوره‌ای ساخته نشده است.');
            }

            return $existing;
        }

        $this->validate([
            'year' => ['required', 'integer', 'min:1400', 'max:1500'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ], [], [
            'year' => 'سال',
            'month' => 'ماه',
        ]);

        $created = $existing === null;
        $period = app(PayrollCalculationService::class)->createOrGetPeriod($this->year, $this->month, auth()->id());

        if ($flash && $created) {
            session()->flash('success', 'دوره حقوق آماده شد.');
        }

        return $period->fresh();
    }

    public function confirmAttendanceAndNext(): void
    {
        abort_unless(auth()->user()?->hasPermission('payroll.calculate') || auth()->user()?->hasPermission('payroll.view'), 403);

        $period = $this->ensurePeriod(flash: false);
        if (! $period) {
            return;
        }

        $attendanceCount = MonthlyAttendance::query()->where('payroll_period_id', $period->id)->count();
        if ($attendanceCount < 1) {
            session()->flash('error', 'برای این ماه کارکردی ثبت نشده است. ابتدا از صفحه محاسبه کارکرد اقدام کنید.');

            return;
        }

        $this->step = 2;
        session()->flash('success', 'کارکرد ماهانه تایید شد. اکنون محاسبه حقوق را اجرا کنید.');
    }

    public function calculatePayroll(): void
    {
        abort_unless(auth()->user()?->hasPermission('payroll.calculate'), 403);

        $period = $this->currentPeriod();
        if (! $period) {
            session()->flash('error', 'ابتدا دوره و کارکرد ماهانه را آماده کنید.');

            return;
        }

        if ($period->status === 'closed') {
            session()->flash('error', 'این دوره بسته شده و امکان محاسبه مجدد ندارد.');

            return;
        }

        try {
            $calculations = app(NewPayrollEngineService::class)->runFullLifecycle($period);
        } catch (PayrollPrerequisiteException $exception) {
            session()->flash('error', $exception->getMessage());

            return;
        }

        $this->step = 2;
        session()->flash('success', 'محاسبه حقوق، بیمه، مالیات و فیش برای ' . formatMoney($calculations->count()) . ' نفر انجام شد.');
    }

    public function confirmPayrollAndNext(): void
    {
        abort_unless(auth()->user()?->hasPermission('payroll.approve'), 403);

        $period = $this->currentPeriod();
        if (! $period) {
            session()->flash('error', 'دوره‌ای برای تایید پیدا نشد.');

            return;
        }

        if ($period->status === 'closed') {
            session()->flash('error', 'این دوره بسته شده است.');

            return;
        }

        if ($period->status === 'approved') {
            $this->step = 3;
            session()->flash('success', 'دوره از قبل تایید شده است. می‌توانید پرداخت‌ها را ثبت کنید.');

            return;
        }

        $failedCount = PayrollCalculation::query()
            ->where('payroll_period_id', $period->id)
            ->where('status', 'failed')
            ->count();
        $okCount = PayrollCalculation::query()
            ->where('payroll_period_id', $period->id)
            ->where('status', '!=', 'failed')
            ->count();

        if ($okCount < 1) {
            session()->flash('error', 'ابتدا محاسبه حقوق را انجام دهید؛ هیچ فیش موفقی برای تایید وجود ندارد.');

            return;
        }

        // Heal legacy/draft periods that already have successful calculations.
        if ($period->status !== 'calculated') {
            $period->update([
                'status' => 'calculated',
                'calculated_at' => $period->calculated_at ?: now(),
            ]);
            $period->refresh();
        }

        app(PayrollCalculationService::class)->approve($period, auth()->id());
        $this->step = 3;

        $message = 'دوره حقوق تایید شد و مرحله پرداخت باز شد.';
        if ($failedCount > 0) {
            $message .= ' توجه: ' . formatMoney($failedCount) . ' نفر محاسبه ناموفق داشتند و در پرداخت نیستند.';
        }

        session()->flash('success', $message);
    }

    public function deletePeriod(): void
    {
        abort_unless(auth()->user()?->hasPermission('payroll.periods.delete'), 403);

        $period = $this->currentPeriod();
        if (! $period) {
            session()->flash('error', 'دوره‌ای برای حذف پیدا نشد.');

            return;
        }

        if (in_array($period->status, ['approved', 'closed'], true)) {
            session()->flash('error', 'دوره تایید شده یا بسته شده قابل حذف نیست.');

            return;
        }

        app(PayrollCalculationService::class)->deletePeriod($period);
        $this->paymentDrafts = [];
        $this->step = 1;
        session()->flash('success', 'دوره حذف شد. می‌توانید از مرحله کارکرد دوباره شروع کنید.');
    }

    public function closePeriod(): void
    {
        abort_unless(auth()->user()?->hasPermission('payroll.periods.close'), 403);

        $period = $this->currentPeriod();
        if (! $period) {
            return;
        }

        if ($period->status !== 'approved') {
            session()->flash('error', 'فقط دوره تاییدشده قابل بستن است.');

            return;
        }

        $period->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        session()->flash('success', 'دوره حقوق بسته شد و فرآیند ماه تمام شد.');
    }

    public function reopenPeriod(): void
    {
        abort_unless(auth()->user()?->hasPermission('payroll.periods.close'), 403);

        $period = $this->currentPeriod();
        if (! $period) {
            return;
        }

        try {
            app(PayrollCalculationService::class)->reopen($period);
        } catch (\RuntimeException $exception) {
            session()->flash('error', $exception->getMessage());

            return;
        }

        $this->step = $this->suggestedStep($period->fresh());
        session()->flash('success', 'دوره حقوق باز شد.');
    }

    public function startEdit(int $calculationId): void
    {
        abort_unless(auth()->user()?->hasPermission('payroll.adjust'), 403);

        $calculation = PayrollCalculation::with(['lines', 'employee.party', 'payments', 'period'])->findOrFail($calculationId);

        if ($calculation->status === 'failed') {
            session()->flash('error', 'فیش ناموفق قابل ویرایش نیست.');

            return;
        }

        if ($calculation->period?->status === 'closed') {
            session()->flash('error', 'دوره بسته‌شده قابل ویرایش نیست.');

            return;
        }

        if ($calculation->payments->isNotEmpty()) {
            session()->flash('error', 'برای فیش پرداخت‌شده ابتدا پرداخت را برگشت دهید.');

            return;
        }

        $this->editingId = $calculation->id;
        $this->editEmployeeName = (string) ($calculation->employee?->full_name ?: 'پرسنل');
        $this->editLines = $calculation->lines
            ->sortBy(fn ($line) => ($line->type === 'earning' ? 0 : 1) . '-' . $line->id)
            ->values()
            ->map(fn ($line) => [
                'id' => $line->id,
                'code' => $line->code,
                'title' => $line->title,
                'type' => $line->type,
                'amount' => (string) (int) round((float) $line->amount),
                'locked' => in_array($line->code, ['insurance', 'tax', 'employee_insurance', 'employer_insurance', 'salary_tax'], true),
            ])
            ->all();

        $this->newBonusAmount = '0';
        $this->newDeductionAmount = '0';
        $this->newDeductionTitle = 'کسورات متفرقه';
        $this->step = 2;
        $this->resetErrorBag();
    }

    public function updatedEditLines($value, $key): void
    {
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

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->editEmployeeName = '';
        $this->editLines = [];
        $this->newBonusAmount = '0';
        $this->newDeductionAmount = '0';
        $this->newDeductionTitle = 'کسورات متفرقه';
        $this->resetValidation();
    }

    public function saveEdit(): void
    {
        abort_unless(auth()->user()?->hasPermission('payroll.adjust'), 403);

        if (! $this->editingId) {
            return;
        }

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
        $this->step = 2;
        session()->flash('success', 'مبالغ حقوق ذخیره شد و سند حسابداری حقوق به‌روز شد.');
    }

    public function openAccountingDocument(int $documentId): void
    {
        abort_unless(auth()->user()?->hasPermission('accounting.view') || auth()->user()?->hasPermission('payroll.view'), 403);

        $this->viewingDocumentId = $documentId;
    }

    public function closeAccountingDocument(): void
    {
        $this->viewingDocumentId = null;
    }

    public function openPaymentModal(int $calculationId): void
    {
        abort_unless(auth()->user()?->hasPermission('payroll.adjust'), 403);

        $calculation = PayrollCalculation::with(['employee.party', 'payments', 'period'])->findOrFail($calculationId);
        $period = $calculation->period;

        if (! $period || $period->status === 'closed') {
            session()->flash('error', 'برای دوره بسته‌شده نمی‌توان پرداخت ثبت یا ویرایش کرد.');

            return;
        }

        if (! in_array($period->status, ['approved', 'closed'], true)) {
            session()->flash('error', 'ابتدا دوره را تایید کنید.');

            return;
        }

        $paid = (float) $calculation->payments->sum('amount');
        $remaining = max(0, (float) $calculation->net_payable - $paid);
        $existingPayment = $calculation->payments->first();
        $draftKey = (string) $calculationId;

        $paymentDateJalali = $existingPayment?->payment_date
            ? formatJalaliDateSafe($existingPayment->payment_date)
            : todayJalaliDate();

        $this->paymentModalCalculationId = $calculationId;
        $this->paymentModalEmployeeName = (string) ($calculation->employee?->full_name ?: 'پرسنل');
        $this->paymentModalDate = $paymentDateJalali;
        $this->paymentDrafts[$draftKey] = [
            'payment_date' => $paymentDateJalali,
            'method' => $existingPayment?->method ?: 'bank',
            'bank_account_id' => $existingPayment?->bank_account_id ?: ($this->defaultBankAccountId ?: ''),
            'cashbox_id' => $existingPayment?->cashbox_id ?: ($this->defaultCashboxId ?: ''),
            'amount' => $existingPayment
                ? (float) $existingPayment->amount
                : ($remaining > 0 ? $remaining : (float) $calculation->net_payable),
            'reference_number' => $existingPayment?->reference_number ?: '',
            'description' => $existingPayment?->description ?: 'پرداخت حقوق',
            'is_edit' => $existingPayment !== null,
        ];
        $this->step = 3;
        $this->resetErrorBag();
    }

    public function closePaymentModal(): void
    {
        if ($this->paymentModalCalculationId) {
            $draftKey = (string) $this->paymentModalCalculationId;
            unset($this->paymentDrafts[$draftKey], $this->paymentDrafts[$this->paymentModalCalculationId]);
        }

        $this->paymentModalCalculationId = null;
        $this->paymentModalEmployeeName = '';
        $this->paymentModalDate = '';
        $this->resetErrorBag();
    }

    public function savePaymentModal(): void
    {
        if (! $this->paymentModalCalculationId) {
            return;
        }

        $this->registerPayment($this->paymentModalCalculationId);
    }

    public function registerPayment(int $calculationId): void
    {
        abort_unless(auth()->user()?->hasPermission('payroll.adjust'), 403);

        $calculation = PayrollCalculation::with(['period', 'employee.party', 'payments'])->findOrFail($calculationId);
        $draftKey = (string) $calculationId;
        $draft = $this->paymentDraftFor($calculationId);

        // Prefer flat modal date (datepicker binds here reliably).
        $jalaliPaymentDate = trim($this->paymentModalDate) !== ''
            ? trim($this->paymentModalDate)
            : trim((string) ($draft['payment_date'] ?? ''));

        if ($draft !== []) {
            $normalizedAmount = normalizeMoneyValue($draft['amount'] ?? null);
            $draft['amount'] = $normalizedAmount === null ? '' : $normalizedAmount;
            $draft['payment_date'] = $jalaliPaymentDate;
            $this->paymentDrafts[$draftKey] = $draft;
        } else {
            $this->paymentDrafts[$draftKey] = array_merge($draft, ['payment_date' => $jalaliPaymentDate]);
        }

        $this->validate([
            'paymentModalDate' => ['required', 'string', 'max:20'],
            "paymentDrafts.$draftKey.method" => ['required', 'in:cash,bank'],
            "paymentDrafts.$draftKey.amount" => ['required', 'numeric', 'min:1'],
            "paymentDrafts.$draftKey.bank_account_id" => ['nullable', 'required_if:paymentDrafts.'.$draftKey.'.method,bank', 'exists:bank_accounts,id'],
            "paymentDrafts.$draftKey.cashbox_id" => ['nullable', 'required_if:paymentDrafts.'.$draftKey.'.method,cash', 'exists:cashboxes,id'],
            "paymentDrafts.$draftKey.reference_number" => ['nullable', 'string', 'max:100'],
            "paymentDrafts.$draftKey.description" => ['nullable', 'string', 'max:500'],
        ], [], [
            'paymentModalDate' => 'تاریخ پرداخت',
            "paymentDrafts.$draftKey.method" => 'روش پرداخت',
            "paymentDrafts.$draftKey.bank_account_id" => 'حساب بانکی',
            "paymentDrafts.$draftKey.cashbox_id" => 'صندوق',
            "paymentDrafts.$draftKey.amount" => 'مبلغ',
        ]);

        $draft = $this->paymentDraftFor($calculationId);
        $draftAmount = normalizeMoneyValue($draft['amount'] ?? null);
        $engine = app(NewPayrollEngineService::class);

        try {
            $payment = DB::transaction(function () use ($engine, $calculation, $draft, $draftAmount, $jalaliPaymentDate) {
                if ($calculation->payments->isNotEmpty()) {
                    $engine->reversePayment($calculation, auth()->id());
                    $calculation->refresh()->load('payments');
                }

                $paymentDate = jalaliToGregorianDate($jalaliPaymentDate);
                if (! $paymentDate) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'paymentModalDate' => 'تاریخ پرداخت نامعتبر است.',
                    ]);
                }

                return $engine->payWithDetails($calculation, [
                    'payment_date' => $paymentDate,
                    'amount' => $draftAmount,
                    'method' => $draft['method'] ?? 'bank',
                    'bank_account_id' => ($draft['bank_account_id'] ?? null) ?: null,
                    'cashbox_id' => ($draft['cashbox_id'] ?? null) ?: null,
                    'reference_number' => $draft['reference_number'] ?? null,
                    'description' => $draft['description'] ?? null,
                ], auth()->id());
            });
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        unset($this->paymentDrafts[$draftKey], $this->paymentDrafts[$calculationId]);
        $this->paymentModalCalculationId = null;
        $this->paymentModalEmployeeName = '';
        $this->paymentModalDate = '';
        $this->step = 3;
        session()->flash('success', 'پرداخت حقوق ذخیره شد. سند: ' . ($payment->accountingDocument?->number ?: '-'));
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

        if ($this->paymentModalCalculationId === $calculationId) {
            $this->closePaymentModal();
        }

        session()->flash('success', 'پرداخت حقوق برگشت داده شد.');
    }

    public function render()
    {
        $period = $this->currentPeriod();
        $this->step = $this->clampStep($this->step);

        $attendances = collect();
        $calculations = collect();
        $summary = [
            'attendance_count' => 0,
            'calculation_count' => 0,
            'success_count' => 0,
            'failed_count' => 0,
            'gross_total' => 0.0,
            'deduction_total' => 0.0,
            'net_total' => 0.0,
            'paid_total' => 0.0,
            'remaining_total' => 0.0,
            'paid_count' => 0,
            'unpaid_count' => 0,
            'workflow_complete' => false,
        ];

        if ($period) {
            $attendances = MonthlyAttendance::query()
                ->with('employee.party')
                ->where('payroll_period_id', $period->id)
                ->orderBy('employee_id')
                ->get();

            $calculations = PayrollCalculation::with([
                'employee.party',
                'period',
                'accountingEntry',
                'accountingDocument',
                'payslip',
                'payments.accountingDocument',
                'attendance',
                'lines',
            ])
                ->withSum('payments', 'amount')
                ->where('payroll_period_id', $period->id)
                ->orderBy('employee_id')
                ->get();

            $summary = $this->buildSummary($attendances, $calculations);
        }

        $bankAccounts = BankAccount::query()->where('is_active', true)->orderBy('bank_name')->get();
        $cashboxes = Cashbox::query()->where('is_active', true)->orderBy('name')->get();

        if ($this->defaultBankAccountId === null) {
            $this->defaultBankAccountId = $bankAccounts->first()?->id;
        }
        if ($this->defaultCashboxId === null) {
            $this->defaultCashboxId = $cashboxes->first()?->id;
        }

        $steps = $this->stepMeta($period, $summary);
        $modalDraftKey = $this->paymentModalCalculationId ? (string) $this->paymentModalCalculationId : null;
        $viewingDocument = $this->viewingDocumentId
            ? AccountingDocument::query()
                ->with(['lines.account', 'lines.detailAccount', 'lines.party', 'lines.project', 'lines.bankAccount'])
                ->find($this->viewingDocumentId)
            : null;

        return view('livewire.payroll.periods', [
            'period' => $period,
            'attendances' => $attendances,
            'calculations' => $calculations,
            'summary' => $summary,
            'steps' => $steps,
            'bankAccounts' => $bankAccounts,
            'cashboxes' => $cashboxes,
            'modalDraftKey' => $modalDraftKey,
            'viewingDocument' => $viewingDocument,
        ]);
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

    private function currentPeriod(): ?PayrollPeriod
    {
        return PayrollPeriod::query()
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->first();
    }

    private function suggestedStep(?PayrollPeriod $period): int
    {
        if (! $period) {
            return 1;
        }

        if (in_array($period->status, ['approved', 'closed'], true)) {
            return 3;
        }

        if ($period->status === 'calculated') {
            return 2;
        }

        $hasAttendance = MonthlyAttendance::query()->where('payroll_period_id', $period->id)->exists();

        return $hasAttendance ? 1 : 1;
    }

    private function clampStep(int $step): int
    {
        $step = max(1, min(3, $step));
        $period = $this->currentPeriod();

        if ($step >= 3) {
            if (! $period || ! in_array($period->status, ['approved', 'closed', 'calculated'], true)) {
                return $period?->status === 'calculated' ? 2 : 1;
            }
            // Allow viewing step 3 only after approve; calculated stays on 2 until confirm.
            if ($period->status === 'calculated') {
                return 2;
            }

            return 3;
        }

        if ($step === 2) {
            $hasAttendance = $period && MonthlyAttendance::query()->where('payroll_period_id', $period->id)->exists();
            if (! $hasAttendance) {
                return 1;
            }
        }

        return $step;
    }

    /**
     * @param  Collection<int, MonthlyAttendance>  $attendances
     * @param  Collection<int, PayrollCalculation>  $calculations
     * @return array<string, mixed>
     */
    private function buildSummary(Collection $attendances, Collection $calculations): array
    {
        $success = $calculations->where('status', '!=', 'failed');
        $failed = $calculations->where('status', 'failed');
        $paidTotal = (float) $calculations->sum(fn ($c) => (float) ($c->payments_sum_amount ?? $c->payments->sum('amount')));
        $netTotal = (float) $success->sum('net_payable');
        $remainingTotal = max(0, $netTotal - $paidTotal);
        $paidCount = $success->filter(function ($c) {
            $paid = (float) ($c->payments_sum_amount ?? $c->payments->sum('amount'));

            return $paid >= (float) $c->net_payable && (float) $c->net_payable > 0;
        })->count();
        $unpaidCount = $success->filter(function ($c) {
            $paid = (float) ($c->payments_sum_amount ?? $c->payments->sum('amount'));

            return max(0, (float) $c->net_payable - $paid) > 0;
        })->count();

        return [
            'attendance_count' => $attendances->count(),
            'calculation_count' => $calculations->count(),
            'success_count' => $success->count(),
            'failed_count' => $failed->count(),
            'gross_total' => (float) $success->sum('gross_salary'),
            'deduction_total' => (float) $success->sum('total_deductions'),
            'net_total' => $netTotal,
            'paid_total' => $paidTotal,
            'remaining_total' => $remainingTotal,
            'paid_count' => $paidCount,
            'unpaid_count' => $unpaidCount,
            'workflow_complete' => $success->count() > 0 && $unpaidCount === 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return list<array<string, mixed>>
     */
    private function stepMeta(?PayrollPeriod $period, array $summary): array
    {
        $hasAttendance = ($summary['attendance_count'] ?? 0) > 0;
        $hasPayroll = ($summary['calculation_count'] ?? 0) > 0;
        $approved = $period && in_array($period->status, ['approved', 'closed'], true);

        return [
            [
                'number' => 1,
                'title' => 'کارکرد ماهانه',
                'description' => 'نمایش و تایید کارکرد',
                'done' => $hasAttendance,
                'current' => $this->step === 1,
                'locked' => false,
            ],
            [
                'number' => 2,
                'title' => 'محاسبه حقوق',
                'description' => 'حقوق، بیمه، مالیات',
                'done' => $hasPayroll && in_array($period?->status, ['calculated', 'approved', 'closed'], true),
                'current' => $this->step === 2,
                'locked' => ! $hasAttendance,
            ],
            [
                'number' => 3,
                'title' => 'پرداخت',
                'description' => 'ثبت و پیگیری پرداخت',
                'done' => (bool) ($summary['workflow_complete'] ?? false),
                'current' => $this->step === 3,
                'locked' => ! $approved,
            ],
        ];
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
