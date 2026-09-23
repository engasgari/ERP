<?php

namespace App\Livewire\Treasury;

use App\Livewire\Core\UI\BaseListPage;
use App\Models\TreasuryTransaction;
use App\Repositories\TreasuryRepository;
use App\Services\TreasuryService;

class Index extends BaseListPage
{
    public string $search = '';

    public string $type = '';

    public string $status = '';

    public string $date_from = '';

    public string $date_to = '';

    public array $typeLabels = [
        'deposit' => 'واریز',
        'withdrawal' => 'برداشت',
        'transfer' => 'انتقال بین حساب‌ها',
        'cash_receipt' => 'دریافت نقدی',
        'cash_payment' => 'پرداخت نقدی',
        'bank_receipt' => 'دریافت بانکی',
        'bank_payment' => 'پرداخت بانکی',
    ];

    public array $statusLabels = [
        'draft' => 'پیش‌نویس',
        'posted' => 'ثبت قطعی',
        'void' => 'باطل',
    ];

    protected array $queryString = [
        'search' => ['except' => ''],
        'type' => ['except' => ''],
        'status' => ['except' => ''],
        'date_from' => ['except' => ''],
        'date_to' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function clearFilters(): void
    {
        $this->reset(['search', 'type', 'status', 'date_from', 'date_to']);
        $this->resetPage();
    }

    public function delete(int $transactionId): void
    {
        $transaction = TreasuryTransaction::findOrFail($transactionId);

        app(TreasuryService::class)->deleteWithAccounting($transaction, auth()->id());

        session()->flash('success', 'تراکنش خزانه و سند حسابداری وابسته حذف شدند.');
    }

    public function render(TreasuryRepository $treasury)
    {
        $dateErrors = [
            'date_from' => $this->date_from !== '' && ! jalaliToGregorianDate($this->date_from) ? 'تاریخ شروع معتبر نیست.' : null,
            'date_to' => $this->date_to !== '' && ! jalaliToGregorianDate($this->date_to) ? 'تاریخ پایان معتبر نیست.' : null,
        ];

        $filters = array_filter([
            'search' => $this->search !== '' ? $this->search : null,
            'type' => $this->type !== '' ? $this->type : null,
            'status' => $this->status !== '' ? $this->status : null,
            'date_from' => $this->date_from !== '' ? jalaliToGregorianDate($this->date_from) : null,
            'date_to' => $this->date_to !== '' ? jalaliToGregorianDate($this->date_to) : null,
        ]);

        $transactions = $treasury->paginate($filters, $this->perPage);

        return view('livewire.treasury.index', compact('transactions', 'dateErrors'));
    }
}
