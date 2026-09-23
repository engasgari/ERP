<?php

namespace App\Services\Crm;

use App\Models\Crm\Opportunity;
use App\Models\Crm\Task;
use App\Models\User;

class CrmTaskService
{
    public function __construct(private readonly CrmAuditService $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Task
    {
        $task = Task::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'assigned_user_id' => $data['assigned_user_id'] ?? $actor->id,
            'due_at' => $data['due_at'] ?? null,
            'priority' => $data['priority'] ?? 'normal',
            'status' => $data['status'] ?? 'pending',
            'party_id' => $data['party_id'] ?? null,
            'taskable_type' => $data['taskable_type'] ?? null,
            'taskable_id' => $data['taskable_id'] ?? null,
            'created_by' => $actor->id,
        ]);

        $this->audit->log($task, 'created', null, $task->only(['title', 'status']), $task->party_id);

        return $task;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Task $task, array $data, User $actor): Task
    {
        if (array_key_exists('status', $data)) {
            if ($data['status'] === 'completed' && $task->status !== 'completed') {
                $data['completed_at'] = now();
            } elseif ($data['status'] !== 'completed') {
                $data['completed_at'] = null;
            }
        }

        $old = $task->only(['title', 'status', 'due_at', 'assigned_user_id']);
        $task->update(collect($data)->only([
            'title', 'description', 'assigned_user_id', 'due_at', 'priority', 'status', 'party_id', 'completed_at',
        ])->all());

        $this->audit->log($task, 'updated', $old, $task->only(['title', 'status', 'due_at', 'assigned_user_id']), $task->party_id);

        return $task->fresh(['party', 'assignedUser']);
    }

    public function complete(Task $task, User $actor): Task
    {
        $this->markCompleted($task);

        if ($task->taskable_type === Opportunity::class && $task->taskable_id) {
            $opportunity = Opportunity::query()->find($task->taskable_id);

            if ($opportunity) {
                app(CrmOpportunityService::class)->tryAdvanceIfStageTasksComplete($opportunity, $actor);
            }
        }

        return $task->fresh();
    }

    public function completeOpenTasksForOpportunity(Opportunity $opportunity, User $actor): int
    {
        $tasks = Task::query()
            ->where('taskable_type', Opportunity::class)
            ->where('taskable_id', $opportunity->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->get();

        foreach ($tasks as $task) {
            $this->markCompleted($task);
        }

        return $tasks->count();
    }

    private function markCompleted(Task $task): void
    {
        $task->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->audit->log($task, 'completed', null, ['status' => 'completed'], $task->party_id);
    }
}
