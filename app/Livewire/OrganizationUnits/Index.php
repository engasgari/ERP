<?php

namespace App\Livewire\OrganizationUnits;

use App\Livewire\Concerns\ResetsPaginationOnFilterChange;
use App\Models\OrganizationUnit;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use ResetsPaginationOnFilterChange;
    use WithPagination;

    public string $search = '';
    public ?int $editingId = null;
    public array $form = ['parent_id' => '', 'code' => '', 'title' => '', 'type' => 'department', 'cost_center_code' => '', 'is_active' => true, 'description' => ''];

    protected $queryString = ['search' => ['except' => ''], 'page' => ['except' => 1]];

    public function rules(): array
    {
        return [
            'form.parent_id' => 'nullable|exists:organization_units,id',
            'form.code' => 'required|string|max:50|unique:organization_units,code,' . $this->editingId,
            'form.title' => 'required|string|max:255',
            'form.type' => 'required|string|max:50',
            'form.cost_center_code' => 'nullable|string|max:100',
            'form.is_active' => 'boolean',
            'form.description' => 'nullable|string',
        ];
    }

    public function clearFilters(): void
    {
        $this->reset('search');
        $this->resetPage();
    }

    public function edit(int $id): void
    {
        $unit = OrganizationUnit::findOrFail($id);
        $this->editingId = $unit->id;
        $this->form = $unit->only(['parent_id', 'code', 'title', 'type', 'cost_center_code', 'is_active', 'description']);
        $this->form['parent_id'] = $this->form['parent_id'] ?: '';
    }

    public function cancel(): void
    {
        $this->editingId = null;
        $this->form = ['parent_id' => '', 'code' => '', 'title' => '', 'type' => 'department', 'cost_center_code' => '', 'is_active' => true, 'description' => ''];
    }

    public function save(): void
    {
        $data = $this->validate()['form'];
        $data['parent_id'] = $data['parent_id'] ?: null;
        $data['is_active'] = (string) $data['is_active'] === '1' || $data['is_active'] === true;

        OrganizationUnit::updateOrCreate(['id' => $this->editingId], $data);
        session()->flash('success', 'واحد سازمانی ذخیره شد.');
        $this->cancel();
    }

    public function delete(int $id): void
    {
        $unit = OrganizationUnit::findOrFail($id);
        if ($unit->children()->exists() || $unit->positions()->exists()) {
            session()->flash('error', 'این واحد دارای زیرمجموعه یا پست سازمانی است و قابل حذف نیست.');
            return;
        }
        $unit->delete();
        session()->flash('success', 'واحد سازمانی حذف شد.');
    }

    public function render()
    {
        $units = OrganizationUnit::with('parent')
            ->when($this->search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('code', 'like', '%' . $this->search . '%')
                ->orWhere('title', 'like', '%' . $this->search . '%')))
            ->orderBy('code')
            ->paginate(15);
        $parents = OrganizationUnit::whereKeyNot($this->editingId ?: 0)->orderBy('title')->get();

        return view('livewire.organization-units.index', compact('units', 'parents'));
    }
}
