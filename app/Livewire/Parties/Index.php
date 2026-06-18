<?php

namespace App\Livewire\Parties;

use App\Livewire\Concerns\ResetsPaginationOnFilterChange;
use App\Models\AccountingDocumentLine;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\PartyType;
use App\Models\Project;
use App\Models\TreasuryTransaction;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class Index extends Component
{
    use ResetsPaginationOnFilterChange;
    use WithPagination;

    public string $search = '';
    public string $kind = '';
    public string $type_id = '';
    public ?int $showingId = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'kind' => ['except' => ''],
        'type_id' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function clearFilters(): void
    {
        $this->reset(['search', 'kind', 'type_id']);
        $this->resetPage();
    }

    public function show(int $partyId): void
    {
        $this->showingId = $partyId;
    }

    public function closeModal(): void
    {
        $this->showingId = null;
    }

    public function delete(int $partyId): void
    {
        $party = Party::findOrFail($partyId);
        $details = $this->relatedDetails($party);

        if (! empty($details)) {
            session()->flash('error', 'به دلیل وجود گردش یا سند مرتبط، امکان حذف این شخص/شرکت وجود ندارد.');
            session()->flash('error_details', $details);
            return;
        }

        try {
            $party->types()->detach();
            $party->delete();
            session()->flash('success', 'شخص/شرکت حذف شد.');
        } catch (Throwable) {
            session()->flash('error', 'به دلیل وجود گردش یا سند مرتبط، امکان حذف این شخص/شرکت وجود ندارد.');
            session()->flash('error_details', ['یک یا چند رکورد وابسته در دیتابیس وجود دارد.']);
        }
    }

    public function updateField(int $partyId, string $field, mixed $value): void
    {
        abort_unless(in_array($field, ['name', 'kind', 'mobile', 'phone', 'postal_code'], true), 403);

        $party = Party::findOrFail($partyId);
        $data = match ($field) {
            'name' => ['name' => trim((string) $value)],
            'kind' => ['kind' => in_array($value, ['person', 'company'], true) ? $value : $party->kind],
            'mobile' => ['mobile' => trim((string) $value) ?: null],
            'phone' => ['phone' => trim((string) $value) ?: null],
            'postal_code' => ['postal_code' => trim((string) $value) ?: null],
        };

        if (($data['name'] ?? $party->name) === '') {
            session()->flash('error', 'نام شخص/شرکت الزامی است.');
            return;
        }

        $party->update($data);
        session()->flash('success', 'تغییرات شخص/شرکت ذخیره شد.');
    }

    public function render()
    {
        $query = Party::with('types')->latest();

        if ($this->search !== '') {
            $search = $this->search;
            $query->where(fn ($q) => $q
                ->where('name', 'like', '%' . $search . '%')
                ->orWhere('code', 'like', '%' . $search . '%')
                ->orWhere('detail_code', 'like', '%' . $search . '%')
                ->orWhere('postal_code', 'like', '%' . $search . '%')
                ->orWhere('mobile', 'like', '%' . $search . '%')
                ->orWhere('phone', 'like', '%' . $search . '%'));
        }

        if ($this->kind !== '') {
            $query->where('kind', $this->kind);
        }

        if ($this->type_id !== '') {
            $query->whereHas('types', fn ($typeQuery) => $typeQuery->where('party_types.id', $this->type_id));
        }

        $parties = $query->paginate(15);
        $types = PartyType::orderBy('title')->get();
        $showingParty = $this->showingId ? Party::with('types')->find($this->showingId) : null;

        return view('livewire.parties.index', compact('parties', 'types', 'showingParty'));
    }

    private function relatedDetails(Party $party): array
    {
        $related = [
            'فاکتور فروش/خرید' => Invoice::where('party_id', $party->id)->count(),
            'ردیف سند حسابداری' => AccountingDocumentLine::where('party_id', $party->id)->count(),
            'پروژه' => Project::where('party_id', $party->id)->count(),
            'تراکنش خزانه' => TreasuryTransaction::withTrashed()->where('party_id', $party->id)->count(),
        ];

        $details = collect($related)->filter(fn ($count) => $count > 0)->map(fn ($count, $title) => "{$title}: {$count}")->values()->all();
        $invoiceNumbers = Invoice::where('party_id', $party->id)->limit(5)->pluck('number')->filter()->implode('، ');
        $projectNames = Project::where('party_id', $party->id)->limit(5)->pluck('name')->filter()->implode('، ');

        if ($invoiceNumbers) {
            $details[] = 'نمونه فاکتورها: ' . $invoiceNumbers;
        }
        if ($projectNames) {
            $details[] = 'نمونه پروژه‌ها: ' . $projectNames;
        }

        return $details;
    }
}
