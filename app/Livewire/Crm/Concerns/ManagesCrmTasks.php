<?php

namespace App\Livewire\Crm\Concerns;

use App\Models\Crm\CrmModel;
use App\Models\Crm\Task;
use App\Models\User;
use App\Services\Crm\CrmTaskService;
use Carbon\Carbon;

trait ManagesCrmTasks
{
    public bool $showTaskModal = false;

    public ?int $editingTaskId = null;

    public string $task_title = '';

    public string $task_description = '';

    public string $task_due_at = '';

    public string $task_priority = 'normal';

    public string $task_status = 'pending';

    public string $task_assigned_user_id = '';

    public ?int $task_party_id = null;

    public ?string $task_taskable_type = null;

    public ?int $task_taskable_id = null;

    public function openTaskModal(
        ?int $partyId = null,
        ?string $taskableType = null,
        ?int $taskableId = null,
    ): void {
        $this->resetTaskForm();
        $this->task_party_id = $partyId;
        $this->task_taskable_type = $taskableType;
        $this->task_taskable_id = $taskableId;
        $this->task_assigned_user_id = (string) auth()->id();
        $this->showTaskModal = true;
    }

    public function openTaskEdit(int $id): void
    {
        $task = Task::query()->findOrFail($id);

        $this->editingTaskId = $task->id;
        $this->task_title = $task->title;
        $this->task_description = $task->description ?? '';
        $this->task_due_at = jalaliDateTimeInputValue($task->due_at);
        $this->task_priority = $task->priority;
        $this->task_status = $task->status;
        $this->task_assigned_user_id = (string) $task->assigned_user_id;
        $this->task_party_id = $task->party_id;
        $this->task_taskable_type = $task->taskable_type;
        $this->task_taskable_id = $task->taskable_id;
        $this->showTaskModal = true;
    }

    public function closeTaskModal(): void
    {
        $this->showTaskModal = false;
        $this->resetTaskForm();
    }

    public function saveTask(CrmTaskService $tasks): void
    {
        $this->validate([
            'task_title' => ['required', 'string', 'max:255'],
            'task_description' => ['nullable', 'string'],
            'task_due_at' => ['nullable', 'string'],
            'task_priority' => ['required', 'string'],
            'task_status' => ['required', 'in:pending,in_progress,completed,cancelled'],
            'task_assigned_user_id' => ['required', 'exists:users,id'],
        ], [], [
            'task_title' => 'عنوان',
            'task_due_at' => 'موعد',
            'task_priority' => 'اولویت',
            'task_status' => 'وضعیت',
            'task_assigned_user_id' => 'مسئول',
        ]);

        $dueAt = $this->parseTaskDueAt($this->task_due_at);

        if ($this->task_due_at !== '' && ! $dueAt) {
            $this->addError('task_due_at', 'موعد معتبر نیست.');

            return;
        }

        $payload = [
            'title' => $this->task_title,
            'description' => $this->task_description ?: null,
            'due_at' => $dueAt,
            'priority' => $this->task_priority,
            'status' => $this->task_status,
            'assigned_user_id' => (int) $this->task_assigned_user_id,
            'party_id' => $this->task_party_id,
        ];

        if ($this->editingTaskId) {
            $task = Task::query()->findOrFail($this->editingTaskId);
            $tasks->update($task, $payload, auth()->user());
            session()->flash('success', 'وظیفه به‌روزرسانی شد.');
        } else {
            $tasks->create(array_merge($payload, [
                'taskable_type' => $this->task_taskable_type,
                'taskable_id' => $this->task_taskable_id,
            ]), auth()->user());
            session()->flash('success', 'وظیفه ثبت شد.');
        }

        $this->closeTaskModal();
    }

    /**
     * @return array{users: \Illuminate\Support\Collection<int, User>, statusOptions: array<string, string>}
     */
    protected function taskFormOptions(): array
    {
        return [
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'statusOptions' => CrmModel::STATUSES_TASK,
        ];
    }

    protected function resetTaskForm(): void
    {
        $this->editingTaskId = null;
        $this->reset([
            'task_title', 'task_description', 'task_due_at', 'task_priority', 'task_status',
            'task_assigned_user_id', 'task_party_id', 'task_taskable_type', 'task_taskable_id',
        ]);
        $this->task_priority = 'normal';
        $this->task_status = 'pending';
        $this->resetValidation();
    }

    protected function parseTaskDueAt(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        $gregorian = jalaliToGregorianDateTime($value);

        if ($gregorian) {
            return Carbon::parse($gregorian);
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
