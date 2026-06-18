<?php

namespace App\Livewire\Projects;

use App\Livewire\Concerns\ResetsPaginationOnFilterChange;
use App\Models\Project;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use ResetsPaginationOnFilterChange;
    use WithPagination;

    public string $name = '';
    public string $status = '';
    public string $start_date = '';
    public string $end_date = '';

    protected $queryString = [
        'name' => ['except' => ''],
        'status' => ['except' => ''],
        'start_date' => ['except' => ''],
        'end_date' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function clearFilters(): void
    {
        $this->reset(['name', 'status', 'start_date', 'end_date']);
        $this->resetPage();
    }

    public function updateField(int $projectId, string $field, mixed $value): void
    {
        abort_unless(in_array($field, ['name', 'status', 'budget'], true), 403);

        $project = Project::findOrFail($projectId);
        $data = match ($field) {
            'name' => ['name' => trim((string) $value)],
            'status' => ['status' => array_key_exists((string) $value, Project::STATUSES) ? (string) $value : $project->status],
            'budget' => ['budget' => $value !== '' ? max(0, (float) $value) : null],
        };

        if (($data['name'] ?? $project->name) === '') {
            session()->flash('error', 'نام پروژه الزامی است.');
            return;
        }

        $project->update($data);
        session()->flash('success', 'تغییرات پروژه ذخیره شد.');
    }

    public function render()
    {
        $query = Project::with('party', 'manager');

        if ($this->name !== '') {
            $name = $this->name;
            $query->where(function ($projectQuery) use ($name) {
                $projectQuery->where('name', 'like', '%' . $name . '%')
                    ->orWhere('project_number', 'like', '%' . $name . '%')
                    ->orWhere('description', 'like', '%' . $name . '%');
            });
        }

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        if ($this->start_date !== '') {
            $startDate = jalaliToGregorianDate($this->start_date);
            if ($startDate) {
                $query->whereDate('start_date', '>=', $startDate);
            }
        }

        if ($this->end_date !== '') {
            $endDate = jalaliToGregorianDate($this->end_date);
            if ($endDate) {
                $query->whereDate('end_date', '<=', $endDate);
            }
        }

        $projects = $query->latest()->paginate(12);
        $dateErrors = [
            'start_date' => $this->start_date !== '' && ! jalaliToGregorianDate($this->start_date) ? 'تاریخ شروع معتبر نیست.' : null,
            'end_date' => $this->end_date !== '' && ! jalaliToGregorianDate($this->end_date) ? 'تاریخ پایان معتبر نیست.' : null,
        ];
        $statuses = Project::STATUSES;

        return view('livewire.projects.index', compact('projects', 'dateErrors', 'statuses'));
    }
}
