<?php

namespace App\Services\Crm;

use App\Models\Crm\Opportunity;
use App\Models\Crm\PipelineStage;
use App\Models\Crm\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CrmPipelineWorkflowService
{
    public function __construct(
        private readonly CrmTaskService $tasks,
        private readonly CrmActivityService $activities,
        private readonly CrmBusinessHoursService $businessHours,
    ) {}

    /**
     * @return list<array{title: string, description?: string, priority: string}>
     */
    public function taskTemplatesForStage(PipelineStage $stage): array
    {
        if ($stage->is_won || $stage->is_lost) {
            return [];
        }

        return match ((int) $stage->sort_order) {
            1 => [
                ['title' => 'تماس اولیه با مشتری', 'description' => 'معرفی شرکت و ثبت نیاز اولیه', 'priority' => 'high'],
            ],
            2 => [
                ['title' => 'ارزیابی اولیه نیاز و بودجه', 'description' => 'تعیین دامنه همکاری و اولویت‌ها', 'priority' => 'normal'],
            ],
            3 => [
                ['title' => 'تحلیل نیاز و تهیه سناریو', 'description' => 'مستندسازی نیازمندی‌های فنی/تجاری', 'priority' => 'normal'],
            ],
            4 => [
                ['title' => 'هماهنگی و برگزاری جلسه', 'description' => 'تنظیم زمان جلسه با ذینفعان', 'priority' => 'high'],
            ],
            5 => [
                ['title' => 'ارسال پیشنهاد قیمت', 'description' => 'تهیه و ارسال پیش‌فاکتور/پروپوزال', 'priority' => 'high'],
            ],
            6 => [
                ['title' => 'پیگیری مذاکره', 'description' => 'پیگیری پاسخ مشتری و اصلاح پیشنهاد', 'priority' => 'urgent'],
            ],
            default => [],
        };
    }

    /**
     * Follow-up due dates stay inside office hours (Sat–Thu, 08:30–17:00).
     */
    public function taskDueAtForStage(PipelineStage $stage, ?Carbon $from = null): Carbon
    {
        $from ??= now();

        return match ((int) $stage->sort_order) {
            1 => $this->businessHours->addBusinessHours($from, 2),
            2 => $this->businessHours->sameOrNextBusinessDayAt($from, 15, 0),
            3 => $this->businessHours->nextBusinessDayAt($from, 2, 10, 0),
            4 => $this->businessHours->nextBusinessDayAt($from, 1, 11, 0),
            5 => $this->businessHours->sameOrNextBusinessDayAt($from, 14, 0),
            6 => $this->businessHours->nextBusinessDayAt($from, 2, 10, 0),
            default => $this->businessHours->nextBusinessDayAt($from, 1, 10, 0),
        };
    }

    /**
     * @return Collection<int, Task>
     */
    public function applyStageTasks(Opportunity $opportunity, PipelineStage $stage, User $actor): Collection
    {
        $created = collect();

        foreach ($this->taskTemplatesForStage($stage) as $template) {
            $exists = Task::query()
                ->where('taskable_type', Opportunity::class)
                ->where('taskable_id', $opportunity->id)
                ->where('title', $template['title'])
                ->whereIn('status', ['pending', 'in_progress'])
                ->exists();

            if ($exists) {
                continue;
            }

            $created->push($this->tasks->create([
                'title' => $template['title'],
                'description' => $template['description'] ?? null,
                'priority' => $template['priority'],
                'due_at' => $this->taskDueAtForStage($stage),
                'party_id' => $opportunity->party_id,
                'taskable_type' => Opportunity::class,
                'taskable_id' => $opportunity->id,
                'assigned_user_id' => $opportunity->assigned_user_id,
            ], $actor));
        }

        return $created;
    }

    public function applyStageSideEffects(Opportunity $opportunity, PipelineStage $stage, User $actor): void
    {
        if ($stage->is_won) {
            $this->tasks->completeOpenTasksForOpportunity($opportunity, $actor);
        }
    }

    public function logStageChangeActivity(Opportunity $opportunity, PipelineStage $stage, User $actor): void
    {
        $this->activities->create([
            'type' => 'follow_up',
            'subject' => 'تغییر مرحله فرصت به: '.$stage->name,
            'description' => 'فرصت '.$opportunity->number.' به مرحله «'.$stage->name.'» منتقل شد.',
            'activitable_type' => Opportunity::class,
            'activitable_id' => $opportunity->id,
            'party_id' => $opportunity->party_id,
            'assigned_user_id' => $opportunity->assigned_user_id,
            'status' => 'completed',
        ], $actor);
    }
}
