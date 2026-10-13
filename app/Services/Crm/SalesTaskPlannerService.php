<?php

namespace App\Services\Crm;

use App\Jobs\Crm\ProcessSalesTaskPlanJob;
use App\Models\Ai\AiRun;
use App\Models\Crm\Lead;
use App\Models\Crm\Opportunity;
use App\Models\Crm\SalesTaskPlan;
use App\Models\Crm\SalesTaskSuggestion;
use App\Models\Crm\Task;
use App\Models\Party;
use App\Models\User;
use App\Repositories\Crm\CrmSalesTaskCandidateRepository;
use App\Services\Ai\AiRunner;
use App\Services\Ai\AiUsageGuard;
use App\Services\Ai\Exceptions\AiException;
use App\Services\Ai\Prompts\AiPromptRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sales Task Planner (Phase 2).
 *
 * Laravel selects follow-up candidates; AI ranks and drafts task text/schedule;
 * humans accept before CrmTaskService creates a real task.
 */
class SalesTaskPlannerService
{
    public const AGENT = 'sales_task_planner';

    public const PERMISSION_REQUEST = 'crm.ai.sales_task_planner.request';

    public const PERMISSION_APPLY = 'crm.ai.sales_task_planner.apply';

    public function __construct(
        private readonly CrmSalesTaskCandidateRepository $candidates,
        private readonly CrmTaskService $tasks,
        private readonly CrmBusinessHoursService $businessHours,
        private readonly CrmScopeService $scope,
        private readonly AiRunner $runner,
        private readonly AiUsageGuard $guard,
        private readonly AiPromptRegistry $prompts,
    ) {}

    public function isEnabled(): bool
    {
        return $this->guard->isAgentEnabled(self::AGENT) && $this->isInstalled();
    }

    public function isInstalled(): bool
    {
        static $installed = null;

        return $installed ??= Schema::hasTable('ai_runs')
            && Schema::hasTable('crm_sales_task_plans')
            && Schema::hasTable('crm_sales_task_suggestions');
    }

    public function canRequest(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission(self::PERMISSION_REQUEST);
    }

    public function canApply(User $user): bool
    {
        return ($user->isAdmin() || $user->hasPermission(self::PERMISSION_APPLY))
            && ($user->isAdmin() || $user->hasPermission('crm.tasks.create'));
    }

    public function latestPlan(User $user): ?SalesTaskPlan
    {
        if (! $this->isInstalled()) {
            return null;
        }

        $query = SalesTaskPlan::query()->latest('id');

        if (! $this->scope->canViewAll($user)) {
            $query->where('requested_by', $user->id);
        }

        return $query->first();
    }

    /**
     * Suggestions for the dashboard, scoped to the viewer.
     *
     * @return Collection<int, SalesTaskSuggestion>
     */
    public function pendingSuggestions(User $user): Collection
    {
        if (! $this->isInstalled()) {
            return collect();
        }

        $query = SalesTaskSuggestion::query()
            ->with(['assignedUser:id,name', 'party:id,name', 'plan:id,status,generated_at,requested_by'])
            ->where('review_status', SalesTaskSuggestion::REVIEW_PENDING)
            ->whereHas('plan', fn ($q) => $q->where('status', SalesTaskPlan::STATUS_COMPLETED))
            ->orderBy('rank')
            ->orderBy('due_at');

        if (! $this->scope->canViewAll($user)) {
            $query->where(function ($q) use ($user) {
                $q->where('assigned_user_id', $user->id)
                    ->orWhereHas('plan', fn ($plan) => $plan->where('requested_by', $user->id));
            });
        }

        return $query->limit(100)->get();
    }

    /**
     * Open tasks grouped by assignee for the dashboard.
     *
     * @return array{groups: list<array{user_id: ?int, user_name: string, tasks: Collection<int, Task>, suggestions: Collection<int, SalesTaskSuggestion>}>}
     */
    public function dashboard(User $user): array
    {
        $tasks = $this->candidates->openTasksForDashboard($user);
        $suggestions = $this->pendingSuggestions($user);

        $userIds = $tasks->pluck('assigned_user_id')
            ->merge($suggestions->pluck('assigned_user_id'))
            ->filter()
            ->unique()
            ->values();

        $names = User::query()->whereIn('id', $userIds)->pluck('name', 'id');

        $groups = [];
        $allUserIds = $userIds->all();
        if ($tasks->contains(fn (Task $t) => ! $t->assigned_user_id) || $suggestions->contains(fn ($s) => ! $s->assigned_user_id)) {
            $allUserIds[] = null;
        }

        foreach ($allUserIds as $uid) {
            $groupTasks = $tasks->where('assigned_user_id', $uid)->values();
            $groupSuggestions = $suggestions->where('assigned_user_id', $uid)->values();
            if ($groupTasks->isEmpty() && $groupSuggestions->isEmpty()) {
                continue;
            }

            $groups[] = [
                'user_id' => $uid,
                'user_name' => $uid ? (string) ($names[$uid] ?? ('کاربر #'.$uid)) : 'بدون مسئول',
                'tasks' => $groupTasks,
                'suggestions' => $groupSuggestions,
            ];
        }

        usort($groups, fn ($a, $b) => strcmp($a['user_name'], $b['user_name']));

        return [
            'groups' => $groups,
            'latest_plan' => $this->latestPlan($user),
            'open_task_count' => $tasks->count(),
            'pending_suggestion_count' => $suggestions->count(),
        ];
    }

    /**
     * @throws AiException
     */
    public function request(User $user): SalesTaskPlan
    {
        $this->guard->assertEnabled(self::AGENT);

        if (! $this->isInstalled()) {
            throw AiException::make(AiException::NOT_CONFIGURED, 'جدول‌های برنامه‌ریز وظایف هنوز ایجاد نشده‌اند (migrate اجرا نشده است).');
        }

        if (! $this->canRequest($user)) {
            throw AiException::make(AiException::UNAUTHORIZED, 'اجازه درخواست برنامه پیگیری هوشمند را ندارید.');
        }

        $lock = Cache::lock('crm-ai:sales-task-planner:'.$user->id, 10);

        if (! $lock->get()) {
            throw AiException::make(AiException::DUPLICATE, 'درخواست دیگری برای برنامه پیگیری در حال ثبت است.');
        }

        try {
            $this->expireStalePending($user->id);

            if (SalesTaskPlan::query()
                ->where('requested_by', $user->id)
                ->where('status', SalesTaskPlan::STATUS_PENDING)
                ->exists()) {
                throw AiException::make(AiException::DUPLICATE, 'برنامه پیگیری دیگری در حال تولید است.');
            }

            $candidateList = $this->candidates->collect(
                $user,
                (int) config('crm_ai.agents.sales_task_planner.max_candidates', 40)
            );

            if ($candidateList === []) {
                throw AiException::make(AiException::INVALID_INPUT, 'کاندیدای پیگیری برای محدوده دسترسی شما یافت نشد.');
            }

            $this->guard->hitRateLimits(self::AGENT, $user->id);
            $prompt = $this->prompts->resolve(self::AGENT);

            $plan = DB::transaction(function () use ($user, $prompt, $candidateList) {
                $run = $this->runner->createRun(
                    $prompt,
                    $user->id,
                    'user',
                    $user->id,
                    self::AGENT.':user:'.$user->id.':'.now()->format('YmdHi'),
                    'sales_candidates',
                );

                return SalesTaskPlan::query()->create([
                    'ai_run_id' => $run->id,
                    'status' => SalesTaskPlan::STATUS_PENDING,
                    'prompt_version' => $prompt->version(),
                    'candidates_count' => count($candidateList),
                    'requested_by' => $user->id,
                ]);
            });

            Cache::put($this->candidateCacheKey($plan->id), $candidateList, now()->addHours(2));
        } finally {
            $lock->release();
        }

        ProcessSalesTaskPlanJob::dispatch($plan->id)
            ->onQueue((string) config('crm_ai.queue', 'default'));

        return $plan;
    }

    public function process(int $planId): void
    {
        $plan = SalesTaskPlan::query()->with('aiRun')->find($planId);

        if (! $plan || $plan->status !== SalesTaskPlan::STATUS_PENDING) {
            return;
        }

        $run = $plan->aiRun;
        $user = User::query()->find($plan->requested_by);

        if (! $run || ! $user || $run->status !== AiRun::STATUS_PENDING) {
            $this->markFailed($plan, 'اجرای مرتبط معتبر نیست یا قبلاً پردازش شده است.');

            return;
        }

        $candidateList = Cache::get($this->candidateCacheKey($plan->id));
        if (! is_array($candidateList) || $candidateList === []) {
            $candidateList = $this->candidates->collect(
                $user,
                (int) config('crm_ai.agents.sales_task_planner.max_candidates', 40)
            );
        }

        if ($candidateList === []) {
            $plan->forceFill([
                'status' => SalesTaskPlan::STATUS_INSUFFICIENT,
                'failure_reason' => 'کاندیدای پیگیری یافت نشد.',
                'generated_at' => now(),
            ])->save();
            $run->forceFill([
                'status' => AiRun::STATUS_CANCELLED,
                'error_code' => AiException::INVALID_INPUT,
                'error_message' => 'کاندیدای پیگیری یافت نشد.',
                'finished_at' => now(),
            ])->save();

            return;
        }

        $prompt = $this->prompts->resolve(self::AGENT, $plan->prompt_version);
        $payload = $this->buildPayload($candidateList);
        $byKey = collect($candidateList)->keyBy('key');

        try {
            $output = $this->runner->execute($run, $prompt, $payload, $this->exemptPaths($candidateList));
            $rows = $this->materializeSuggestions($output, $byKey);

            DB::transaction(function () use ($plan, $run, $output, $rows) {
                foreach ($rows as $row) {
                    SalesTaskSuggestion::query()->create($row + ['plan_id' => $plan->id]);
                }

                $plan->forceFill([
                    'status' => $rows === [] ? SalesTaskPlan::STATUS_INSUFFICIENT : SalesTaskPlan::STATUS_COMPLETED,
                    'model' => $run->fresh()?->model,
                    'suggestions_count' => count($rows),
                    'planner_note' => $output['planner_note'] ?? null,
                    'generated_at' => now(),
                    'failure_reason' => $rows === [] ? 'هیچ پیشنهاد معتبری از خروجی مدل باقی نماند.' : null,
                ])->save();
            });
        } catch (AiException $e) {
            $this->markFailed($plan, $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $this->markFailed($plan, 'خطای غیرمنتظره در تولید برنامه پیگیری.');
        } finally {
            Cache::forget($this->candidateCacheKey($plan->id));
        }
    }

    /**
     * Accept a suggestion and create a CRM task (or reschedule an overdue task).
     *
     * @throws AiException
     */
    public function apply(SalesTaskSuggestion $suggestion, User $user): Task
    {
        if (! $this->canApply($user)) {
            throw AiException::make(AiException::UNAUTHORIZED, 'اجازه ایجاد وظیفه از پیشنهاد هوش مصنوعی را ندارید.');
        }

        if ($suggestion->review_status !== SalesTaskSuggestion::REVIEW_PENDING) {
            throw AiException::make(AiException::INVALID_INPUT, 'این پیشنهاد قبلاً بررسی شده است.');
        }

        if (! $this->scope->canViewAll($user)
            && $suggestion->assigned_user_id !== $user->id
            && $suggestion->plan?->requested_by !== $user->id) {
            throw AiException::make(AiException::UNAUTHORIZED, 'به این پیشنهاد دسترسی ندارید.');
        }

        return DB::transaction(function () use ($suggestion, $user) {
            if ($suggestion->candidate_kind === 'overdue_task' && $suggestion->subject_type === Task::class) {
                $task = Task::query()->findOrFail($suggestion->subject_id);
                $task = $this->tasks->update($task, [
                    'due_at' => $suggestion->due_at,
                    'priority' => $suggestion->priority,
                    'status' => $task->status === 'completed' ? 'pending' : $task->status,
                    'description' => trim(($task->description ? $task->description."\n\n" : '').'پیگیری هوشمند: '.($suggestion->reason ?? '')),
                ], $user);
            } else {
                $taskableType = in_array($suggestion->subject_type, [Lead::class, Opportunity::class], true)
                    ? $suggestion->subject_type
                    : null;

                $task = $this->tasks->create([
                    'title' => $suggestion->title,
                    'description' => trim(($suggestion->description ?: '')."\n\nعلت پیشنهاد: ".($suggestion->reason ?? '')),
                    'assigned_user_id' => $suggestion->assigned_user_id ?: $user->id,
                    'due_at' => $suggestion->due_at,
                    'priority' => $suggestion->priority,
                    'status' => 'pending',
                    'party_id' => $suggestion->party_id,
                    'taskable_type' => $taskableType,
                    'taskable_id' => $taskableType ? $suggestion->subject_id : null,
                ], $user);
            }

            $suggestion->forceFill([
                'review_status' => SalesTaskSuggestion::REVIEW_APPLIED,
                'applied_task_id' => $task->id,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
            ])->save();

            return $task;
        });
    }

    /**
     * @throws AiException
     */
    public function reject(SalesTaskSuggestion $suggestion, User $user): void
    {
        if (! $this->canApply($user) && ! $this->canRequest($user)) {
            throw AiException::make(AiException::UNAUTHORIZED, 'اجازه رد پیشنهاد را ندارید.');
        }

        if ($suggestion->review_status !== SalesTaskSuggestion::REVIEW_PENDING) {
            throw AiException::make(AiException::INVALID_INPUT, 'این پیشنهاد قبلاً بررسی شده است.');
        }

        $suggestion->forceFill([
            'review_status' => SalesTaskSuggestion::REVIEW_REJECTED,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ])->save();
    }

    /**
     * @param  list<array<string, mixed>>  $candidateList
     * @return array<string, mixed>
     */
    private function buildPayload(array $candidateList): array
    {
        return [
            'as_of' => now()->toIso8601String(),
            'timezone' => (string) config('app.timezone', 'UTC'),
            'business_hours' => [
                'start' => sprintf('%02d:%02d', CrmBusinessHoursService::OFFICE_START_HOUR, CrmBusinessHoursService::OFFICE_START_MINUTE),
                'end' => sprintf('%02d:%02d', CrmBusinessHoursService::OFFICE_END_HOUR, CrmBusinessHoursService::OFFICE_END_MINUTE),
                'closed_weekdays' => ['friday'],
            ],
            'candidates' => array_map(fn (array $c) => [
                'key' => $c['key'],
                'kind' => $c['kind'],
                'party_name' => $c['party_name'],
                'subject_label' => $c['subject_label'],
                'assigned_user_id' => $c['assigned_user_id'],
                'assigned_user_name' => $c['assigned_user_name'],
                'days_idle' => $c['days_idle'],
                'amount_tier' => $c['amount_tier'],
                'stage_name' => $c['stage_name'],
                'has_open_task' => $c['has_open_task'],
                'facts' => $c['facts'],
            ], $candidateList),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $candidateList
     * @return list<string>
     */
    private function exemptPaths(array $candidateList): array
    {
        $exempt = ['as_of', 'timezone', 'business_hours.start', 'business_hours.end'];
        foreach (array_keys($candidateList) as $i) {
            $exempt[] = "candidates.{$i}.key";
            $exempt[] = "candidates.{$i}.kind";
            $exempt[] = "candidates.{$i}.subject_label";
            $exempt[] = "candidates.{$i}.party_name";
            $exempt[] = "candidates.{$i}.stage_name";
        }

        return $exempt;
    }

    /**
     * @param  array<string, mixed>  $output
     * @param  Collection<string, array<string, mixed>>  $byKey
     * @return list<array<string, mixed>>
     */
    private function materializeSuggestions(array $output, Collection $byKey): array
    {
        $max = (int) config('crm_ai.agents.sales_task_planner.max_suggestions', 20);
        $seen = [];
        $rows = [];

        $items = collect($output['suggestions'] ?? [])
            ->sortBy(fn ($s) => (int) ($s['rank'] ?? 999))
            ->values();

        foreach ($items as $item) {
            $key = (string) ($item['candidate_key'] ?? '');
            if ($key === '' || ! $byKey->has($key) || isset($seen[$key])) {
                continue;
            }

            $candidate = $byKey->get($key);
            $seen[$key] = true;

            if (! empty($candidate['has_open_task']) && ($candidate['kind'] ?? '') !== 'overdue_task') {
                continue;
            }

            $offset = max(1, min(120, (int) ($item['due_offset_business_hours'] ?? 4)));
            $dueAt = $this->businessHours->addBusinessHours(now(), $offset);

            $rows[] = [
                'candidate_key' => $key,
                'candidate_kind' => $candidate['kind'],
                'subject_type' => $candidate['subject_type'],
                'subject_id' => $candidate['subject_id'],
                'party_id' => $candidate['party_id'],
                'assigned_user_id' => $candidate['assigned_user_id'],
                'title' => mb_substr((string) $item['title'], 0, 255),
                'description' => $item['description'] ?? null,
                'priority' => $item['priority'] ?? 'normal',
                'due_at' => $dueAt,
                'due_offset_business_hours' => $offset,
                'rank' => (int) ($item['rank'] ?? count($rows) + 1),
                'reason' => $item['reason'] ?? null,
                'supporting_facts' => $candidate['facts'] ?? [],
                'review_status' => SalesTaskSuggestion::REVIEW_PENDING,
            ];

            if (count($rows) >= $max) {
                break;
            }
        }

        return $rows;
    }

    private function expireStalePending(int $userId): void
    {
        $lockSeconds = max(60, (int) config('crm_ai.limits.lock_seconds', 300));
        $cutoff = now()->subSeconds($lockSeconds);

        SalesTaskPlan::query()
            ->where('requested_by', $userId)
            ->where('status', SalesTaskPlan::STATUS_PENDING)
            ->where('created_at', '<', $cutoff)
            ->update([
                'status' => SalesTaskPlan::STATUS_FAILED,
                'failure_reason' => 'درخواست منقضی شد.',
            ]);
    }

    private function markFailed(SalesTaskPlan $plan, string $reason): void
    {
        $plan->forceFill([
            'status' => SalesTaskPlan::STATUS_FAILED,
            'failure_reason' => mb_substr($reason, 0, 500),
            'generated_at' => now(),
        ])->save();
    }

    private function candidateCacheKey(int $planId): string
    {
        return 'crm-ai:sales-task-planner:candidates:'.$planId;
    }
}
