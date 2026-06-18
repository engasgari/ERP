<?php

namespace App\Livewire\Payroll;

use App\Exceptions\PayrollPrerequisiteException;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\PayrollCalculation;
use App\Services\NewPayrollEngineService;
use App\Services\PayrollCalculationService;
use Livewire\Component;
use Livewire\WithPagination;

class Periods extends Component
{
    use WithPagination;

    public int $year = 0;
    public int $month = 0;
    public string $notes = '';
    public int $perPage = 12;
    public array $paymentDrafts = [];

    protected $queryString = [
        'year' => ['except' => 0],
        'month' => ['except' => 0],
        'page' => ['except' => 1],
    ];

    public function mount(): void
    {
        $requestedYear = (int) request()->integer('year', 0);
        $requestedMonth = (int) request()->integer('month', 0);
        $todayParts = explode('/', formatJalaliDateSafe(now()));

        $this->year = $requestedYear > 0
            ? $requestedYear
            : (int) ($todayParts[0] ?? 1405);

        $this->month = $requestedMonth > 0
            ? $requestedMonth
            : (int) ($todayParts[1] ?? 1);
    }

    public function updated($name): void
    {
        if (in_array($name, ['year', 'month', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function createPeriod(): void
    {
        $this->validatePeriod();

        $service = app(PayrollCalculationService::class);
        $period = $service->createOrGetPeriod($this->year, $this->month, auth()->id());
        $period->update(['notes' => $this->notes ?: $period->notes]);

        session()->flash('success', 'دوره حقوق با موفقیت آماده شد.');
    }

    public function calculate(int $periodId): void
    {
        $period = PayrollPeriod::findOrFail($periodId);

        if ($period->status === 'closed') {
            session()->flash('error', 'این دوره بسته شده و امکان محاسبه مجدد ندارد.');
            return;
        }

        try {
            $service = app(NewPayrollEngineService::class);
            $calculations = $service->runFullLifecycle($period);
        } catch (PayrollPrerequisiteException $exception) {
            session()->flash('error', $exception->getMessage());
            return;
        }

        session()->flash('success', 'پردازش حضور، حقوق، بیمه، مالیات و فیش برای ' . $calculations->count() . ' نفر انجام شد.');
    }

    public function approve(int $periodId): void
    {
        $period = PayrollPeriod::findOrFail($periodId);

        if ($period->status !== 'calculated') {
            session()->flash('error', 'فقط دوره محاسبه‌شده قابل تایید است.');
            return;
        }

        app(PayrollCalculationService::class)->approve($period, auth()->id());

        session()->flash('success', 'دوره حقوق تایید شد.');
    }

    public function deletePeriod(int $periodId): void
    {
        $period = PayrollPeriod::findOrFail($periodId);

        if (in_array($period->status, ['approved', 'closed'], true)) {
            session()->flash('error', 'دوره تایید شده یا بسته شده قابل حذف نیست.');
            return;
        }

        app(PayrollCalculationService::class)->deletePeriod($period);

        session()->flash('success', 'دوره محاسبه‌نشده حذف شد و می‌توانید محاسبه را دوباره انجام دهید.');
    }

    public function close(int $periodId): void
    {
        $period = PayrollPeriod::findOrFail($periodId);

        if ($period->status !== 'approved') {
            session()->flash('error', 'فقط دوره تاییدشده قابل بستن است.');
            return;
        }

        $period->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        session()->flash('success', 'دوره حقوق بسته شد.');
    }

    public function registerPayment(int $calculationId): void
    {
        $calculation = PayrollCalculation::with(['period', 'employee.party', 'payments'])->findOrFail($calculationId);

        $this->validate([
            "paymentDrafts.$calculationId.payment_date" => ['required', 'string', 'max:20'],
            "paymentDrafts.$calculationId.method" => ['required', 'in:cash,bank'],
            "paymentDrafts.$calculationId.amount" => ['nullable', 'numeric', 'min:1'],
            "paymentDrafts.$calculationId.reference_number" => ['nullable', 'string', 'max:100'],
            "paymentDrafts.$calculationId.description" => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $payment = app(NewPayrollEngineService::class)->payWithDetails($calculation, [
                'payment_date' => jalaliToGregorianDate($this->paymentDrafts[$calculationId]['payment_date']) ?: now()->toDateString(),
                'amount' => $this->paymentDrafts[$calculationId]['amount'] ?? null,
                'method' => $this->paymentDrafts[$calculationId]['method'] ?? 'bank',
                'reference_number' => $this->paymentDrafts[$calculationId]['reference_number'] ?? null,
                'description' => $this->paymentDrafts[$calculationId]['description'] ?? null,
            ], auth()->id());
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
            return;
        }

        unset($this->paymentDrafts[$calculationId]);

        session()->flash('success', 'پرداخت حقوق ثبت شد و سند حسابداری پرداخت شماره ' . ($payment->accountingDocument?->number ?: '-') . ' ایجاد شد.');
    }

    public function render()
    {
        $periods = PayrollPeriod::withCount(['attendanceCalculations', 'salaries'])
            ->withCount(['monthlyAttendances', 'payrollCalculations'])
            ->withCount([
                'payrollCalculations as failed_payroll_calculations_count' => fn ($query) => $query->where('status', 'failed'),
                'payrollCalculations as successful_payroll_calculations_count' => fn ($query) => $query->where('status', 'calculated'),
            ])
            ->withSum('salaries', 'final_salary')
            ->withSum('payrollCalculations', 'net_payable')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate($this->perPage);

        $calculations = PayrollCalculation::with(['employee.party', 'period', 'accountingEntry', 'accountingDocument', 'payslip', 'payments.accountingDocument'])
            ->withSum('payments', 'amount')
            ->whereHas('period', function ($query): void {
                $query->where('year', $this->year)->where('month', $this->month);
            })
            ->orderByDesc('employee_id')
            ->get();

        $items = PayrollItem::orderBy('type')
            ->orderBy('sort_order')
            ->get();

        return view('livewire.payroll.periods', compact('periods', 'items', 'calculations'));
    }

    private function validatePeriod(): void
    {
        $this->validate([
            'year' => ['required', 'integer', 'min:1400', 'max:1500'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'year' => 'سال',
            'month' => 'ماه',
            'notes' => 'توضیحات',
        ]);
    }
}
