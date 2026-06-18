<?php

namespace App\Livewire\Positions;

use App\Livewire\Concerns\ResetsPaginationOnFilterChange;
use App\Models\Job;
use App\Models\OrganizationUnit;
use App\Models\Position;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use ResetsPaginationOnFilterChange;
    use WithPagination;

    public string $search = '';
    public ?int $editingId = null;
    public array $form = ['code' => '', 'title' => '', 'job_id' => '', 'organization_unit_id' => '', 'supervisor_position_id' => '', 'capacity' => 1, 'is_active' => true, 'description' => ''];

    protected $queryString = ['search' => ['except' => ''], 'page' => ['except' => 1]];

    public function rules(): array
    {
        return [
            'form.code' => 'required|string|max:50|unique:positions,code,' . $this->editingId,
            'form.title' => 'required|string|max:255',
            'form.job_id' => 'nullable|exists:hr_jobs,id',
            'form.organization_unit_id' => 'nullable|exists:organization_units,id',
            'form.supervisor_position_id' => 'nullable|exists:positions,id',
            'form.capacity' => 'required|integer|min:1|max:999',
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
        $position = Position::findOrFail($id);
        $this->editingId = $position->id;
        $this->form = $position->only(['code', 'title', 'job_id', 'organization_unit_id', 'supervisor_position_id', 'capacity', 'is_active', 'description']);
        foreach (['job_id', 'organization_unit_id', 'supervisor_position_id'] as $key) {
            $this->form[$key] = $this->form[$key] ?: '';
        }
    }

    public function cancel(): void
    {
        $this->editingId = null;
        $this->form = ['code' => '', 'title' => '', 'job_id' => '', 'organization_unit_id' => '', 'supervisor_position_id' => '', 'capacity' => 1, 'is_active' => true, 'description' => ''];
    }

    public function save(): void
    {
        $data = $this->validate()['form'];
        foreach (['job_id', 'organization_unit_id', 'supervisor_position_id'] as $key) {
            $data[$key] = $data[$key] ?: null;
        }
        $data['is_active'] = (string) $data['is_active'] === '1' || $data['is_active'] === true;
        Position::updateOrCreate(['id' => $this->editingId], $data);
        session()->flash('success', 'پست سازمانی ذخیره شد.');
        $this->cancel();
    }

    public function delete(int $id): void
    {
        $position = Position::findOrFail($id);
        if ($position->employees()->exists()) {
            session()->flash('error', 'این پست به پرسنل تخصیص داده شده است.');
            return;
        }
        $position->delete();
        session()->flash('success', 'پست سازمانی حذف شد.');
    }

    public function render()
    {
        $positions = Position::with(['job', 'organizationUnit', 'supervisor'])
            ->when($this->search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('code', 'like', '%' . $this->search . '%')
                ->orWhere('title', 'like', '%' . $this->search . '%')))
            ->orderBy('title')
            ->paginate(15);
        $jobs = Job::where('is_active', true)->orderBy('title')->get();
        $units = OrganizationUnit::where('is_active', true)->orderBy('title')->get();
        $supervisors = Position::whereKeyNot($this->editingId ?: 0)->orderBy('title')->get();

        return view('livewire.positions.index', compact('positions', 'jobs', 'units', 'supervisors'));
    }
}
