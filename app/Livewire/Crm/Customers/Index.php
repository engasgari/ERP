<?php

namespace App\Livewire\Crm\Customers;

use App\Livewire\Core\UI\BaseListPage;
use App\Repositories\Crm\CrmCustomerRepository;

class Index extends BaseListPage
{
    public string $search = '';

    public string $status = '';

    protected array $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
    ];

    public function clearFilters(): void
    {
        $this->reset(['search', 'status']);
        $this->resetPage();
    }

    public function render(CrmCustomerRepository $customers)
    {
        $filters = array_filter([
            'search' => $this->search !== '' ? $this->search : null,
            'status' => $this->status !== '' ? $this->status : null,
        ]);

        $items = $customers->paginate($filters, auth()->user(), $this->perPage);

        return view('livewire.crm.customers.index', compact('items'));
    }
}
