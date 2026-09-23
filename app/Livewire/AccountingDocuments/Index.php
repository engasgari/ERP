<?php

namespace App\Livewire\AccountingDocuments;

use App\Livewire\Core\UI\BaseListPage;
use App\Repositories\AccountingDocumentRepository;

class Index extends BaseListPage
{
    public string $search = '';

    public string $type = '';

    public string $status = '';

    public string $date_from = '';

    public string $date_to = '';

    public array $typeLabels = [
        'manual' => 'دستی',
        'sale_invoice' => 'فاکتور فروش',
        'purchase_invoice' => 'فاکتور خرید',
        'payment' => 'پرداخت',
        'receipt' => 'دریافت',
        'inventory' => 'انبار',
        'opening' => 'افتتاحیه',
        'closing' => 'اختتامیه',
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

    public function render(AccountingDocumentRepository $documents)
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

        $items = $documents->paginate($filters, $this->perPage);

        return view('livewire.accounting-documents.index', compact('items', 'dateErrors'));
    }
}
