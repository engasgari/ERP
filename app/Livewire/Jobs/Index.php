<?php

namespace App\Livewire\Jobs;

use App\Livewire\Concerns\ResetsPaginationOnFilterChange;
use App\Models\Job;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use ResetsPaginationOnFilterChange;
    use WithPagination;

    public string $search = '';
    public ?int $editingId = null;
    public array $form = ['code' => '', 'title' => '', 'description' => '', 'is_active' => true];

    protected $queryString = ['search' => ['except' => ''], 'page' => ['except' => 1]];

    public function rules(): array
    {
        return [
            'form.code' => 'required|string|max:50|unique:hr_jobs,code,' . $this->editingId,
            'form.title' => 'required|string|max:255',
            'form.description' => 'nullable|string',
            'form.is_active' => 'boolean',
        ];
    }

    public function clearFilters(): void
    {
        $this->reset('search');
        $this->resetPage();
    }

    public function edit(int $id): void
    {
        $job = Job::findOrFail($id);
        $this->editingId = $job->id;
        $this->form = $job->only(['code', 'title', 'description', 'is_active']);
    }

    public function cancel(): void
    {
        $this->editingId = null;
        $this->form = ['code' => '', 'title' => '', 'description' => '', 'is_active' => true];
    }

    public function save(): void
    {
        $data = $this->validate()['form'];
        $data['is_active'] = (string) $data['is_active'] === '1' || $data['is_active'] === true;
        Job::updateOrCreate(['id' => $this->editingId], $data);
        session()->flash('success', 'شغل ذخیره شد.');
        $this->cancel();
    }

    public function delete(int $id): void
    {
        $job = Job::findOrFail($id);
        if ($job->positions()->exists()) {
            session()->flash('error', 'این شغل در پست سازمانی استفاده شده است.');
            return;
        }
        $job->delete();
        session()->flash('success', 'شغل حذف شد.');
    }

    public function render()
    {
        $jobs = Job::when($this->search !== '', fn ($query) => $query->where(fn ($q) => $q
            ->where('code', 'like', '%' . $this->search . '%')
            ->orWhere('title', 'like', '%' . $this->search . '%')))
            ->orderBy('title')
            ->paginate(15);

        return view('livewire.jobs.index', compact('jobs'));
    }
}
