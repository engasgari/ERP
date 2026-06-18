<?php

namespace App\Livewire\EmploymentContracts;

use App\Livewire\Concerns\ResetsPaginationOnFilterChange;
use App\Models\EmploymentContract;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use ResetsPaginationOnFilterChange;
    use WithPagination;

    public string $search = '';
    public ?int $editingId = null;
    public string $body = '';

    protected $queryString = ['search' => ['except' => ''], 'page' => ['except' => 1]];

    public function clearFilters(): void
    {
        $this->reset('search');
        $this->resetPage();
    }

    public function editBody(int $id): void
    {
        $contract = EmploymentContract::findOrFail($id);
        $this->editingId = $contract->id;
        $this->body = (string) $contract->body;
    }

    public function saveBody(): void
    {
        if ($this->editingId === null) {
            session()->flash('error', 'لطفا ابتدا یک قرارداد را انتخاب کنید.');
            return;
        }
        EmploymentContract::findOrFail($this->editingId)->update(['body' => $this->body]);
        $this->editingId = null;
        $this->body = '';
        session()->flash('success', 'متن قرارداد ذخیره شد.');
    }

    public function issue(int $id): void
    {
        EmploymentContract::findOrFail($id)->update(['status' => 'issued']);
        session()->flash('success', 'قرارداد صادر شد.');
    }

    public function render()
    {
        $contracts = EmploymentContract::with(['employee.party', 'employmentOrder'])
            ->when($this->search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('number', 'like', '%' . $this->search . '%')
                ->orWhereHas('employee.party', fn ($party) => $party->where('name', 'like', '%' . $this->search . '%'))))
            ->latest()
            ->paginate(12);

        return view('livewire.employment-contracts.index', compact('contracts'));
    }
}
