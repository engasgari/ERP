<?php

namespace App\Livewire\FinancialTransactions;

use App\Livewire\Core\UI\BaseListPage;
use App\Repositories\FinancialTransactionRepository;

class Index extends BaseListPage
{
    public string $project_id = '';

    public string $bank_account_id = '';

    public string $cashbox_id = '';

    public string $type = '';

    public string $category = '';

    public string $start_date = '';

    public string $end_date = '';

    public string $amount_min = '';

    public string $amount_max = '';

    public string $description = '';

    protected array $queryString = [
        'project_id' => ['except' => ''],
        'bank_account_id' => ['except' => ''],
        'cashbox_id' => ['except' => ''],
        'type' => ['except' => ''],
        'category' => ['except' => ''],
        'start_date' => ['except' => ''],
        'end_date' => ['except' => ''],
        'amount_min' => ['except' => ''],
        'amount_max' => ['except' => ''],
        'description' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function clearFilters(): void
    {
        $this->reset([
            'project_id',
            'bank_account_id',
            'cashbox_id',
            'type',
            'category',
            'start_date',
            'end_date',
            'amount_min',
            'amount_max',
            'description',
        ]);
        $this->resetPage();
    }

    public function render(FinancialTransactionRepository $transactions)
    {
        $dateErrors = [
            'start_date' => $this->start_date !== '' && ! jalaliToGregorianDate($this->start_date) ? 'تاریخ شروع معتبر نیست.' : null,
            'end_date' => $this->end_date !== '' && ! jalaliToGregorianDate($this->end_date) ? 'تاریخ پایان معتبر نیست.' : null,
        ];

        $filters = array_filter([
            'project_id' => $this->project_id !== '' ? $this->project_id : null,
            'bank_account_id' => $this->bank_account_id !== '' ? $this->bank_account_id : null,
            'cashbox_id' => $this->cashbox_id !== '' ? $this->cashbox_id : null,
            'type' => $this->type !== '' ? $this->type : null,
            'category' => $this->category !== '' ? $this->category : null,
            'start_date' => $this->start_date !== '' ? jalaliToGregorianDate($this->start_date) : null,
            'end_date' => $this->end_date !== '' ? jalaliToGregorianDate($this->end_date) : null,
            'amount_min' => $this->amount_min !== '' ? normalizePersianDigits($this->amount_min) : null,
            'amount_max' => $this->amount_max !== '' ? normalizePersianDigits($this->amount_max) : null,
            'description' => $this->description !== '' ? $this->description : null,
        ]);

        $items = $transactions->paginate($filters, $this->perPage);
        $summary = $transactions->summarize($filters);
        $filterOptions = $transactions->filterOptions();

        return view('livewire.financial-transactions.index', [
            'items' => $items,
            'summary' => $summary,
            'dateErrors' => $dateErrors,
            'projects' => $filterOptions['projects'],
            'bankAccounts' => $filterOptions['bankAccounts'],
            'cashboxes' => $filterOptions['cashboxes'],
            'categories' => $filterOptions['categories'],
        ]);
    }
}
