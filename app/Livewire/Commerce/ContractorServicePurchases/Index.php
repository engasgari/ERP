<?php

namespace App\Livewire\Commerce\ContractorServicePurchases;

use App\Livewire\Core\UI\BaseListPage;
use App\Models\Invoice;
use App\Repositories\ContractorServicePurchaseRepository;
use App\Services\ContractorServicePurchaseService;
use Livewire\Attributes\Url;

class Index extends BaseListPage
{
    public int $perPage = 500;

    #[Url]
    public string $search = '';

    #[Url]
    public string $project_id = '';

    #[Url]
    public string $fiscal_year_id = '';

    #[Url]
    public string $contractor_party_id = '';

    #[Url]
    public bool $include_purchased = false;

    /** @var list<int> */
    public array $selectedSaleIds = [];

    public function updated(string $name): void
    {
        if ($name === 'selectedSaleIds' || str_starts_with($name, 'selectedSaleIds.')) {
            $this->selectedSaleIds = array_values(array_unique(array_map(
                static fn ($id) => (int) $id,
                $this->selectedSaleIds,
            )));

            return;
        }

        if ($name !== 'selectedRows' && $name !== 'detailsId') {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'project_id', 'fiscal_year_id', 'contractor_party_id', 'include_purchased', 'selectedSaleIds']);
        $this->resetPage();
    }

    public function assignContractor(int $saleInvoiceId, mixed $contractorPartyId, ContractorServicePurchaseService $service): void
    {
        if ($contractorPartyId === '' || $contractorPartyId === null) {
            return;
        }

        $sale = Invoice::query()->findOrFail($saleInvoiceId);
        $service->assignContractor($sale, (int) $contractorPartyId, auth()->user());

    }

    public function createPurchaseInvoice(ContractorServicePurchaseService $service)
    {
        $purchase = $service->createPurchaseFromSales($this->selectedSaleIds, auth()->user());

        $this->selectedSaleIds = [];

        session()->flash('success', 'فاکتور خرید خدمات به صورت موقت ایجاد شد. مبالغ را ویرایش و سپس تأیید کنید.');

        return redirect()->route('invoices.edit', $purchase);
    }

    public function render(ContractorServicePurchaseRepository $repository): mixed
    {
        $filters = [
            'search' => $this->search !== '' ? $this->search : null,
            'project_id' => $this->project_id !== '' ? (int) $this->project_id : null,
            'fiscal_year_id' => $this->fiscal_year_id !== '' ? (int) $this->fiscal_year_id : null,
            'contractor_party_id' => $this->contractor_party_id !== '' ? $this->contractor_party_id : null,
            'include_purchased' => $this->include_purchased,
        ];

        $items = $repository->paginateServiceSaleInvoices($filters, auth()->user(), $this->perPage);

        return view('livewire.commerce.contractor-service-purchases.index', [
            'items' => $items,
            'contractors' => $repository->contractorParties(),
            'projects' => $repository->projectsForFilter(),
            'fiscalYears' => $repository->fiscalYearsForFilter(),
        ]);
    }
}
