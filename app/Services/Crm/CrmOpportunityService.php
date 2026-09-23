<?php

namespace App\Services\Crm;

use App\Models\Crm\Audit;
use App\Models\Crm\Opportunity;
use App\Models\Crm\PipelineStage;
use App\Models\Crm\Task;
use App\Models\Party;
use App\Models\User;
use App\Services\NumberingService;
use Illuminate\Support\Facades\DB;

class CrmOpportunityService
{
    public function __construct(
        private readonly NumberingService $numbering,
        private readonly CrmAuditService $audit,
        private readonly CrmPipelineWorkflowService $pipelineWorkflow,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Opportunity
    {
        $probability = (float) ($data['probability'] ?? 0);
        $amount = (float) ($data['amount'] ?? 0);

        if ($probability <= 0 && ! empty($data['stage_id'])) {
            $stage = PipelineStage::query()->find($data['stage_id']);
            $probability = (float) ($stage?->probability ?? 0);
        }

        $opportunity = Opportunity::create([
            'number' => $this->numbering->next('crm_opportunity', 'OPP-'),
            'title' => $data['title'],
            'party_id' => $data['party_id'],
            'contact_id' => $data['contact_id'] ?? null,
            'lead_id' => $data['lead_id'] ?? null,
            'pipeline_id' => $data['pipeline_id'],
            'stage_id' => $data['stage_id'],
            'assigned_user_id' => $data['assigned_user_id'],
            'amount' => $amount,
            'probability' => $probability,
            'expected_amount' => $this->expectedAmount($amount, $probability),
            'expected_close_date' => $data['expected_close_date'] ?? null,
            'source_id' => $data['source_id'] ?? null,
            'status' => 'open',
            'description' => $data['description'] ?? null,
            'created_by' => $actor->id,
        ]);

        $this->audit->log($opportunity, 'created', null, $opportunity->only(['title', 'stage_id', 'amount']), $opportunity->party_id);

        $stage = PipelineStage::query()->find($opportunity->stage_id);
        if ($stage) {
            $this->pipelineWorkflow->applyStageTasks($opportunity, $stage, $actor);
            $this->pipelineWorkflow->applyStageSideEffects($opportunity->fresh(), $stage, $actor);
        }

        return $opportunity;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Opportunity $opportunity, array $data, User $actor): Opportunity
    {
        $amount = (float) ($data['amount'] ?? $opportunity->amount);
        $probability = array_key_exists('probability', $data)
            ? (float) $data['probability']
            : (float) $opportunity->probability;

        if ($probability <= 0 && $opportunity->status === 'open' && isset($data['stage_id'])) {
            $stage = PipelineStage::query()->find($data['stage_id']);
            $probability = (float) ($stage?->probability ?? $opportunity->probability);
        }

        $fields = [
            'title' => $data['title'],
            'party_id' => $data['party_id'],
            'contact_id' => $data['contact_id'] ?? null,
            'source_id' => $data['source_id'] ?? null,
            'description' => $data['description'] ?? null,
            'assigned_user_id' => $data['assigned_user_id'],
            'expected_close_date' => $data['expected_close_date'] ?? null,
            'amount' => $amount,
            'probability' => $probability,
            'expected_amount' => $this->expectedAmount($amount, $probability),
            'updated_by' => $actor->id,
        ];

        $newStageId = isset($data['stage_id']) ? (int) $data['stage_id'] : (int) $opportunity->stage_id;
        $stageChanged = $opportunity->status === 'open' && $newStageId !== (int) $opportunity->stage_id;

        if ($opportunity->status === 'open' && ! $stageChanged) {
            $fields['pipeline_id'] = $data['pipeline_id'] ?? $opportunity->pipeline_id;
            $fields['stage_id'] = $newStageId;
        }

        $old = $opportunity->only(['title', 'amount', 'stage_id', 'assigned_user_id']);
        $opportunity->update($fields);

        $this->audit->log($opportunity, 'updated', $old, $opportunity->only(['title', 'amount', 'stage_id', 'assigned_user_id']), $opportunity->party_id);

        if ($stageChanged) {
            $stage = PipelineStage::query()->findOrFail($newStageId);

            return $this->changeStage($opportunity->fresh(), $stage, $actor);
        }

        return $opportunity->fresh(['party', 'stage', 'assignedUser', 'pipeline', 'contact']);
    }

    public function changeStage(Opportunity $opportunity, PipelineStage $stage, User $actor): Opportunity
    {
        $oldStageId = $opportunity->stage_id;
        $status = 'open';
        $wonAt = null;
        $lostAt = null;

        if ($stage->is_won) {
            $status = 'won';
            $wonAt = now();
        } elseif ($stage->is_lost) {
            $status = 'lost';
            $lostAt = now();
        }

        $opportunity->update([
            'stage_id' => $stage->id,
            'pipeline_id' => $stage->pipeline_id,
            'probability' => $stage->probability,
            'expected_amount' => $this->expectedAmount((float) $opportunity->amount, (float) $stage->probability),
            'status' => $status,
            'won_at' => $wonAt,
            'lost_at' => $lostAt,
            'updated_by' => $actor->id,
        ]);

        $this->audit->log($opportunity, 'stage_changed', ['stage_id' => $oldStageId], ['stage_id' => $stage->id], $opportunity->party_id);

        if ($oldStageId !== $stage->id) {
            $this->pipelineWorkflow->logStageChangeActivity($opportunity, $stage, $actor);
            $this->pipelineWorkflow->applyStageTasks($opportunity, $stage, $actor);
            $this->pipelineWorkflow->applyStageSideEffects($opportunity->fresh(), $stage, $actor);
        }

        return $opportunity->fresh(['stage', 'party', 'invoice']);
    }

    public function tryAdvanceIfStageTasksComplete(Opportunity $opportunity, User $actor): ?Opportunity
    {
        if ($opportunity->status !== 'open') {
            return null;
        }

        $openTasks = Task::query()
            ->where('taskable_type', Opportunity::class)
            ->where('taskable_id', $opportunity->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->count();

        if ($openTasks > 0) {
            return null;
        }

        $currentStage = PipelineStage::query()->find($opportunity->stage_id);

        if (! $currentStage) {
            return null;
        }

        $nextStage = PipelineStage::query()
            ->where('pipeline_id', $opportunity->pipeline_id)
            ->where('is_won', false)
            ->where('is_lost', false)
            ->where('sort_order', '>', $currentStage->sort_order)
            ->orderBy('sort_order')
            ->first();

        if (! $nextStage) {
            return null;
        }

        return $this->changeStage($opportunity->fresh(), $nextStage, $actor);
    }

    public function reopenFromWon(Opportunity $opportunity, User $actor, ?PipelineStage $targetStage = null): Opportunity
    {
        if ($opportunity->status !== 'won') {
            throw new \InvalidArgumentException('فقط فرصت‌های برنده‌شده قابل بازگشت به مرحله قبل هستند.');
        }

        $wonStageId = (int) $opportunity->stage_id;
        $targetStage ??= $this->resolveOpenStageBeforeWon($opportunity, $wonStageId);

        if ($targetStage->pipeline_id !== (int) $opportunity->pipeline_id) {
            throw new \InvalidArgumentException('مرحله مقصد باید در همان پایپ‌لاین فرصت باشد.');
        }

        if ($targetStage->is_won || $targetStage->is_lost) {
            throw new \InvalidArgumentException('مرحله مقصد نمی‌تواند برنده یا از دست رفته باشد.');
        }

        $opportunity->update([
            'stage_id' => $targetStage->id,
            'status' => 'open',
            'won_at' => null,
            'probability' => $targetStage->probability,
            'expected_amount' => $this->expectedAmount((float) $opportunity->amount, (float) $targetStage->probability),
            'updated_by' => $actor->id,
        ]);

        $this->audit->log(
            $opportunity,
            'stage_changed',
            ['stage_id' => $wonStageId, 'status' => 'won'],
            ['stage_id' => $targetStage->id, 'status' => 'open', 'reopened_from_won' => true],
            $opportunity->party_id,
        );

        $this->pipelineWorkflow->logStageChangeActivity($opportunity->fresh(), $targetStage, $actor);
        $this->pipelineWorkflow->applyStageTasks($opportunity, $targetStage, $actor);

        return $opportunity->fresh(['stage', 'party', 'invoice', 'assignedUser', 'pipeline']);
    }

    private function resolveOpenStageBeforeWon(Opportunity $opportunity, int $wonStageId): PipelineStage
    {
        $audit = Audit::query()
            ->where('auditable_type', Opportunity::class)
            ->where('auditable_id', $opportunity->id)
            ->where('event', 'stage_changed')
            ->where('new_values->stage_id', $wonStageId)
            ->latest('created_at')
            ->first();

        $previousStageId = isset($audit->old_values['stage_id']) ? (int) $audit->old_values['stage_id'] : null;

        if ($previousStageId) {
            $stage = PipelineStage::query()->find($previousStageId);

            if ($stage && ! $stage->is_won && ! $stage->is_lost) {
                return $stage;
            }
        }

        return PipelineStage::query()
            ->where('pipeline_id', $opportunity->pipeline_id)
            ->where('is_won', false)
            ->where('is_lost', false)
            ->orderByDesc('sort_order')
            ->firstOrFail();
    }

    public function deleteOpen(Opportunity $opportunity, User $actor): void
    {
        if ($opportunity->status !== 'open') {
            throw new \InvalidArgumentException('فقط فرصت‌های باز قابل حذف هستند.');
        }

        if ($opportunity->invoice_id) {
            throw new \InvalidArgumentException('فرصتی که پیش‌فاکتور دارد قابل حذف نیست؛ ابتدا ارتباط پیش‌فاکتور را بررسی کنید.');
        }

        if ($opportunity->project_id) {
            throw new \InvalidArgumentException('فرصتی که به پروژه متصل است قابل حذف نیست.');
        }

        DB::transaction(function () use ($opportunity, $actor) {
            $this->audit->log(
                $opportunity,
                'deleted',
                $opportunity->only(['number', 'title', 'status', 'stage_id']),
                null,
                $opportunity->party_id,
            );

            $opportunity->update(['updated_by' => $actor->id]);
            $opportunity->delete();
        });
    }

    public function closeOpenForParty(Party $party, User $actor, ?string $lostReason = null): int
    {
        $reason = $lostReason ?: 'بسته‌شده';

        $closed = Opportunity::query()
            ->where('party_id', $party->id)
            ->where('status', 'open')
            ->get();

        foreach ($closed as $opportunity) {
            $opportunity->update([
                'status' => 'lost',
                'lost_at' => now(),
                'lost_reason' => $reason,
                'updated_by' => $actor->id,
            ]);

            $this->audit->log(
                $opportunity,
                'closed',
                ['status' => 'open'],
                ['status' => 'lost', 'lost_reason' => $reason],
                $party->id,
            );
        }

        return $closed->count();
    }

    public function expectedAmount(float $amount, float $probability): float
    {
        return round($amount * ($probability / 100), 2);
    }
}
