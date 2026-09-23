<?php

namespace App\Livewire\Insurance;

use App\Models\BankAccount;
use App\Models\Cashbox;
use App\Models\InsurancePayment;
use App\Services\InsuranceLiabilityService;
use App\Services\InsurancePaymentService;
use Livewire\Component;
use Livewire\WithPagination;

class Payments extends Component
{
    use WithPagination;

    public bool $showCreateForm = false;

    public string $paymentDate = '';

    public string $method = 'bank';

    public string $bankAccountId = '';

    public string $cashboxId = '';

    public string $referenceNumber = '';

    public string $paymentIdentifier = '';

    public string $receiptNumber = '';

    public string $description = '';

    /** @var array<int, array<string, mixed>> */
    public array $lineDrafts = [];

    protected $queryString = [
        'page' => ['except' => 1],
    ];

    public function mount(): void
    {
        $this->paymentDate = todayJalaliDate();
        $this->initializeLineDrafts();
    }

    public function toggleCreateForm(): void
    {
        $this->showCreateForm = ! $this->showCreateForm;

        if ($this->showCreateForm) {
            $this->initializeLineDrafts();
        }
    }

    public function registerPayment(): void
    {
        $this->validate([
            'paymentDate' => ['required', 'string', 'max:20'],
            'method' => ['required', 'in:cash,bank'],
            'bankAccountId' => ['nullable', 'required_if:method,bank', 'exists:bank_accounts,id'],
            'cashboxId' => ['nullable', 'required_if:method,cash', 'exists:cashboxes,id'],
            'referenceNumber' => ['nullable', 'string', 'max:100'],
            'paymentIdentifier' => ['nullable', 'string', 'max:100'],
            'receiptNumber' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'lineDrafts.*.principal_amount' => ['nullable', 'numeric', 'min:0'],
            'lineDrafts.*.penalty_amount' => ['nullable', 'numeric', 'min:0'],
            'lineDrafts.*.other_amount' => ['nullable', 'numeric', 'min:0'],
        ], [], [
            'paymentDate' => 'تاریخ پرداخت',
            'method' => 'روش پرداخت',
            'bankAccountId' => 'حساب بانکی',
            'cashboxId' => 'صندوق',
        ]);

        $lines = collect($this->lineDrafts)
            ->filter(fn ($line) => ! empty($line['selected']))
            ->map(fn ($line) => [
                'insurance_liability_id' => (int) $line['insurance_liability_id'],
                'principal_amount' => (float) ($line['principal_amount'] ?? 0),
                'penalty_amount' => (float) ($line['penalty_amount'] ?? 0),
                'other_amount' => (float) ($line['other_amount'] ?? 0),
            ])
            ->values()
            ->all();

        try {
            $payment = app(InsurancePaymentService::class)->create([
                'payment_date' => jalaliToGregorianDate($this->paymentDate) ?: now()->toDateString(),
                'method' => $this->method,
                'bank_account_id' => $this->method === 'bank' ? $this->bankAccountId : null,
                'cashbox_id' => $this->method === 'cash' ? $this->cashboxId : null,
                'reference_number' => $this->referenceNumber ?: null,
                'payment_identifier' => $this->paymentIdentifier ?: null,
                'receipt_number' => $this->receiptNumber ?: null,
                'description' => $this->description ?: null,
            ], $lines, auth()->id());
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->showCreateForm = false;
        $this->resetForm();
        session()->flash('success', 'پرداخت بیمه ثبت شد. شماره: ' . $payment->number);
    }

    public function deletePayment(int $paymentId): void
    {
        $payment = InsurancePayment::findOrFail($paymentId);

        try {
            app(InsurancePaymentService::class)->delete($payment, auth()->id());
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        session()->flash('success', 'پرداخت بیمه حذف شد و مانده بدهی بازگردانده شد.');
    }

    public function fillRemaining(int $liabilityId): void
    {
        $liability = app(InsuranceLiabilityService::class)
            ->payableLiabilities()
            ->firstWhere('id', $liabilityId);

        if (! $liability) {
            return;
        }

        $paid = (float) $liability->paid_amount;
        $principal = (float) $liability->principal_amount;
        $penalty = (float) $liability->penalty_amount;
        $other = (float) $liability->other_amount;

        $paidToPrincipal = min($paid, $principal);
        $remainingPaid = max(0, $paid - $principal);
        $paidToPenalty = min($remainingPaid, $penalty);
        $remainingPaid = max(0, $remainingPaid - $penalty);
        $paidToOther = min($remainingPaid, $other);

        $this->lineDrafts[$liabilityId] = array_merge($this->lineDrafts[$liabilityId] ?? [], [
            'selected' => true,
            'principal_amount' => max(0, round($principal - $paidToPrincipal, 2)),
            'penalty_amount' => max(0, round($penalty - $paidToPenalty, 2)),
            'other_amount' => max(0, round($other - $paidToOther, 2)),
        ]);
    }

    public function render()
    {
        $payments = InsurancePayment::query()
            ->with(['bankAccount', 'cashbox', 'accountingDocument', 'creator', 'lines.period'])
            ->latest('payment_date')
            ->latest('id')
            ->paginate(15);

        $payableLiabilities = app(InsuranceLiabilityService::class)->payableLiabilities();
        $bankAccounts = BankAccount::query()->where('is_active', true)->orderBy('bank_name')->get();
        $cashboxes = Cashbox::query()->where('is_active', true)->orderBy('name')->get();

        foreach ($payableLiabilities as $liability) {
            if (! isset($this->lineDrafts[$liability->id])) {
                $this->lineDrafts[$liability->id] = [
                    'insurance_liability_id' => $liability->id,
                    'selected' => false,
                    'principal_amount' => 0,
                    'penalty_amount' => 0,
                    'other_amount' => 0,
                ];
            }
        }

        return view('livewire.insurance.payments', compact('payments', 'payableLiabilities', 'bankAccounts', 'cashboxes'));
    }

    private function initializeLineDrafts(): void
    {
        $this->lineDrafts = [];
        foreach (app(InsuranceLiabilityService::class)->payableLiabilities() as $liability) {
            $this->lineDrafts[$liability->id] = [
                'insurance_liability_id' => $liability->id,
                'selected' => false,
                'principal_amount' => 0,
                'penalty_amount' => 0,
                'other_amount' => 0,
            ];
        }
    }

    private function resetForm(): void
    {
        $this->paymentDate = todayJalaliDate();
        $this->method = 'bank';
        $this->bankAccountId = '';
        $this->cashboxId = '';
        $this->referenceNumber = '';
        $this->paymentIdentifier = '';
        $this->receiptNumber = '';
        $this->description = '';
        $this->initializeLineDrafts();
        $this->resetValidation();
    }
}
