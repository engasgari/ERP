<?php

namespace App\Livewire\Invoices;

use App\Livewire\Core\UI\BaseListPage;
use App\Repositories\InvoiceRepository;

class Index extends BaseListPage
{
    public string $search = '';

    public string $direction = '';

    public string $document_type = '';

    public string $status = '';

    public string $date_from = '';

    public string $date_to = '';

    public ?int $showingId = null;

    protected array $queryString = [
        'search' => ['except' => ''],
        'direction' => ['except' => ''],
        'document_type' => ['except' => ''],
        'status' => ['except' => ''],
        'date_from' => ['except' => ''],
        'date_to' => ['except' => ''],
    ];

    public function clearFilters(): void
    {
        $this->reset(['search', 'direction', 'document_type', 'status', 'date_from', 'date_to']);
        $this->resetPage();
    }

    public function show(int $invoiceId): void
    {
        $this->showingId = $invoiceId;
    }

    public function closeModal(): void
    {
        $this->showingId = null;
    }

    public function render(InvoiceRepository $invoices)
    {
        $dateErrors = [
            'date_from' => $this->date_from !== '' && ! jalaliToGregorianDate($this->date_from) ? 'تاریخ شروع معتبر نیست.' : null,
            'date_to' => $this->date_to !== '' && ! jalaliToGregorianDate($this->date_to) ? 'تاریخ پایان معتبر نیست.' : null,
        ];

        $filters = array_filter([
            'search' => $this->search !== '' ? $this->search : null,
            'direction' => $this->direction !== '' ? $this->direction : null,
            'document_type' => $this->document_type !== '' ? $this->document_type : null,
            'status' => $this->status !== '' ? $this->status : null,
            'date_from' => $this->date_from !== '' ? jalaliToGregorianDate($this->date_from) : null,
            'date_to' => $this->date_to !== '' ? jalaliToGregorianDate($this->date_to) : null,
        ]);

        $items = $invoices->paginate($filters, $this->perPage);
        $showingInvoice = $this->showingId ? $invoices->findForDetails($this->showingId) : null;

        return view('livewire.invoices.index', compact('items', 'dateErrors', 'showingInvoice'));
    }
}
