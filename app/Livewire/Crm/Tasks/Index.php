<?php

namespace App\Livewire\Crm\Tasks;

use App\Livewire\Core\UI\BaseListPage;
use App\Livewire\Crm\Concerns\ManagesCrmTasks;
use App\Models\Crm\CrmModel;
use App\Models\Crm\Task;
use App\Models\Party;
use App\Repositories\Crm\CrmTaskRepository;
use App\Services\Crm\CrmTaskService;

class Index extends BaseListPage
{
    use ManagesCrmTasks;

    public string $search = '';

    public string $status = '';

    public string $priority = '';

    public string $form_party_id = '';

    protected array $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
        'priority' => ['except' => ''],
    ];

    public function openCreate(): void
    {
        $this->form_party_id = '';
        $this->openTaskModal();
    }

    public function openEdit(int $id): void
    {
        $this->openTaskEdit($id);
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'priority']);
        $this->resetPage();
    }

    public function complete(int $id, CrmTaskService $tasks): void
    {
        $task = Task::query()->findOrFail($id);
        $tasks->complete($task, auth()->user());
        session()->flash('success', 'وظیفه انجام شد.');
    }

    public function storeTask(CrmTaskService $tasks): void
    {
        if ($this->form_party_id !== '') {
            $this->task_party_id = (int) $this->form_party_id;
        }

        $this->saveTask($tasks);
    }

    public function render(CrmTaskRepository $tasks)
    {
        $filters = array_filter([
            'search' => $this->search !== '' ? $this->search : null,
            'status' => $this->status !== '' ? $this->status : null,
            'priority' => $this->priority !== '' ? $this->priority : null,
        ]);

        $items = $tasks->paginate($filters, auth()->user(), $this->perPage);
        $statusOptions = CrmModel::STATUSES_TASK;
        $priorityOptions = CrmModel::PRIORITIES_TASK;
        $customers = Party::query()->customers()->orderBy('name')->limit(300)->get(['id', 'name', 'code']);

        return view('livewire.crm.tasks.index', array_merge(
            compact('items', 'statusOptions', 'priorityOptions', 'customers'),
            $this->taskFormOptions(),
        ));
    }
}
