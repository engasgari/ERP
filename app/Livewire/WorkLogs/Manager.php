<?php

namespace App\Livewire\WorkLogs;

use App\Models\Project;
use App\Models\WorkLog;
use Hekmatinasser\Verta\Verta;
use Livewire\Component;
use Livewire\WithPagination;

class Manager extends Component
{
    use WithPagination;

    public string $employee = '';
    public string $project = '';
    public string $startDateFa = '';
    public string $endDateFa = '';
    public int $perPage = 20;

    protected $queryString = [
        'employee' => ['except' => ''],
        'project' => ['except' => ''],
        'startDateFa' => ['except' => ''],
        'endDateFa' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function updated($name): void
    {
        if (in_array($name, ['employee', 'project', 'startDateFa', 'endDateFa', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['employee', 'project', 'startDateFa', 'endDateFa']);
        $this->resetPage();
    }

    public function updateProject(int $workLogId, string $projectId): void
    {
        if ($projectId === '') {
            session()->flash('error', 'انتخاب پروژه برای کارکرد الزامی است.');
            return;
        }

        $workLog = WorkLog::findOrFail($workLogId);
        $workLog->update(['project_id' => $projectId]);

        session()->flash('success', 'پروژه کارکرد به‌روزرسانی شد.');
    }

    public function removeWorkLog(int $workLogId): void
    {
        WorkLog::findOrFail($workLogId)->delete();
        $this->resetPage();
        session()->flash('success', 'کارکرد حذف شد.');
    }

    public function render()
    {
        $query = $this->filteredQuery();

        $workLogs = $query->latest()->paginate($this->perPage);
        $projects = Project::orderBy('name')->get();

        return view('livewire.work-logs.manager', compact('workLogs', 'projects'));
    }

    private function filteredQuery()
    {
        $query = WorkLog::with(['employee', 'project']);

        if ($this->employee !== '') {
            $query->whereHas('employee', function ($employeeQuery) {
                $employeeQuery->where('first_name', 'like', '%' . $this->employee . '%')
                    ->orWhere('last_name', 'like', '%' . $this->employee . '%')
                    ->orWhere('national_code', 'like', '%' . $this->employee . '%');
            });
        }

        if ($this->project !== '') {
            $query->where('project_id', $this->project);
        }

        if ($this->startDateFa !== '') {
            try {
                $query->where('work_date', '>=', Verta::parse(str_replace('/', '-', $this->startDateFa))->datetime());
            } catch (\Throwable) {
            }
        }

        if ($this->endDateFa !== '') {
            try {
                $query->where('work_date', '<=', Verta::parse(str_replace('/', '-', $this->endDateFa))->datetime());
            } catch (\Throwable) {
            }
        }

        return $query;
    }
}
