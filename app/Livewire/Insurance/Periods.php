<?php

namespace App\Livewire\Insurance;

use App\Models\InsuranceLiability;
use App\Models\InsurancePeriod;
use App\Services\InsuranceLiabilityService;
use Livewire\Component;
use Livewire\WithPagination;

class Periods extends Component
{
    use WithPagination;

    public int $year = 0;

    public string $statusFilter = '';

    public ?int $editingLiabilityId = null;

    public string $penaltyAmount = '0';

    public string $otherAmount = '0';

    protected $queryString = [
        'year' => ['except' => 0],
        'statusFilter' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function mount(): void
    {
        $this->year = (int) request()->integer('year', (int) getCurrentPersianYear());
        if ($this->year < 1400 || $this->year > 1500) {
            $this->year = (int) getCurrentPersianYear();
        }
    }

    public function updated($name): void
    {
        if (in_array($name, ['year', 'statusFilter'], true)) {
            $this->resetPage();
            $this->cancelEdit();
        }
    }

    public function syncAll(): void
    {
        try {
            $count = app(InsuranceLiabilityService::class)->syncAll($this->year);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        session()->flash('success', 'بدهی بیمه برای ' . $count . ' دوره همگام‌سازی شد.');
    }

    public function startEdit(int $liabilityId): void
    {
        $liability = InsuranceLiability::with('period')->findOrFail($liabilityId);
        $this->editingLiabilityId = $liability->id;
        $this->penaltyAmount = (string) (float) $liability->penalty_amount;
        $this->otherAmount = (string) (float) $liability->other_amount;
    }

    public function cancelEdit(): void
    {
        $this->editingLiabilityId = null;
        $this->penaltyAmount = '0';
        $this->otherAmount = '0';
        $this->resetValidation();
    }

    public function saveManualAmounts(): void
    {
        if (! $this->editingLiabilityId) {
            return;
        }

        $this->validate([
            'penaltyAmount' => ['required', 'numeric', 'min:0'],
            'otherAmount' => ['required', 'numeric', 'min:0'],
        ], [], [
            'penaltyAmount' => 'جریمه',
            'otherAmount' => 'سایر مبالغ',
        ]);

        $liability = InsuranceLiability::findOrFail($this->editingLiabilityId);

        try {
            app(InsuranceLiabilityService::class)->updateManualAmounts($liability, [
                'penalty_amount' => (float) $this->penaltyAmount,
                'other_amount' => (float) $this->otherAmount,
            ]);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->cancelEdit();
        session()->flash('success', 'مبالغ جریمه/سایر برای دوره ذخیره شد.');
    }

    public function render()
    {
        $summary = app(InsuranceLiabilityService::class)->summary($this->year, $this->statusFilter ?: null);

        $periods = InsurancePeriod::query()
            ->with('liability')
            ->where('year', $this->year)
            ->when($this->statusFilter !== '', function ($query): void {
                $query->whereHas('liability', fn ($liability) => $liability->where('status', $this->statusFilter));
            })
            ->orderByDesc('month')
            ->paginate(12);

        return view('livewire.insurance.periods', compact('periods', 'summary'));
    }
}
