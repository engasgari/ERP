<?php

namespace App\Repositories\Crm;

use App\Models\Crm\Activity;
use App\Models\Crm\Lead;
use App\Models\Crm\Opportunity;
use App\Models\Crm\Task;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\User;
use App\Services\Crm\CrmScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Deterministic follow-up candidates for the Sales Task Planner. No AI logic here.
 */
class CrmSalesTaskCandidateRepository
{
    public function __construct(private readonly CrmScopeService $scope) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function collect(User $user, int $limit = 40): array
    {
        $cfg = config('crm_ai.agents.sales_task_planner', []);
        $oppIdle = max(1, (int) ($cfg['opportunity_idle_days'] ?? 3));
        $proformaIdle = max(1, (int) ($cfg['proforma_idle_days'] ?? 3));
        $customerIdle = max(1, (int) ($cfg['customer_idle_days'] ?? 30));
        $nearClose = max(1, (int) ($cfg['near_close_days'] ?? 14));
        $highAmount = (float) ($cfg['high_amount_threshold'] ?? 500_000_000);

        $openTaskPartyIds = $this->openTaskPartyIds($user);
        $openTaskSubjectKeys = $this->openTaskSubjectKeys($user);

        $candidates = collect()
            ->merge($this->newLeads($user, $openTaskSubjectKeys))
            ->merge($this->idleOpportunities($user, $oppIdle, $openTaskSubjectKeys, $highAmount, $nearClose))
            ->merge($this->idleProformas($user, $proformaIdle, $openTaskSubjectKeys))
            ->merge($this->overdueTasks($user))
            ->merge($this->dormantCustomers($user, $customerIdle, $openTaskPartyIds));

        return $candidates
            ->unique('key')
            ->sortBy([
                fn (array $c) => match ($c['kind']) {
                    'overdue_task' => 0,
                    'near_close_opportunity' => 1,
                    'high_value_opportunity' => 2,
                    'proforma_idle' => 3,
                    'opportunity_idle' => 4,
                    'new_lead_no_contact' => 5,
                    default => 6,
                },
                fn (array $c) => -((int) ($c['days_idle'] ?? 0)),
            ])
            ->take(max(1, $limit))
            ->values()
            ->all();
    }

    /**
     * Open CRM tasks for the dashboard (deterministic, not AI).
     *
     * @return Collection<int, Task>
     */
    public function openTasksForDashboard(User $user, int $limit = 100): Collection
    {
        $query = Task::query()
            ->with(['assignedUser:id,name', 'party:id,name'])
            ->whereIn('status', ['pending', 'in_progress'])
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->limit($limit);

        $this->scope->applyOwnerScope($query, $user);

        return $query->get();
    }

    /** @return array<int, true> */
    private function openTaskPartyIds(User $user): array
    {
        $query = Task::query()
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereNotNull('party_id');
        $this->scope->applyOwnerScope($query, $user);

        return $query->pluck('party_id')->unique()->mapWithKeys(fn ($id) => [(int) $id => true])->all();
    }

    /** @return array<string, true> */
    private function openTaskSubjectKeys(User $user): array
    {
        $query = Task::query()
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereNotNull('taskable_type')
            ->whereNotNull('taskable_id');
        $this->scope->applyOwnerScope($query, $user);

        $keys = [];
        foreach ($query->get(['taskable_type', 'taskable_id']) as $task) {
            $keys[$this->subjectKey($task->taskable_type, (int) $task->taskable_id)] = true;
        }

        return $keys;
    }

    /** @return list<array<string, mixed>> */
    private function newLeads(User $user, array $openTaskSubjectKeys): array
    {
        $query = Lead::query()
            ->with('assignedUser:id,name')
            ->where('status', 'new')
            ->whereNull('converted_at')
            ->orderBy('created_at')
            ->limit(30);
        $this->scope->applyOwnerScope($query, $user);

        $out = [];
        foreach ($query->get() as $lead) {
            $hasActivity = Activity::query()
                ->where('activitable_type', Lead::class)
                ->where('activitable_id', $lead->id)
                ->where('status', 'completed')
                ->exists();

            if ($hasActivity) {
                continue;
            }

            $subjectKey = $this->subjectKey(Lead::class, $lead->id);
            $out[] = $this->candidate(
                key: 'lead:'.$lead->id,
                kind: 'new_lead_no_contact',
                subjectType: Lead::class,
                subjectId: $lead->id,
                partyId: $lead->party_id,
                partyName: $lead->company_name ?: trim(($lead->first_name ?? '').' '.($lead->last_name ?? '')),
                subjectLabel: $lead->number ?: ('سرنخ #'.$lead->id),
                assignedUserId: $lead->assigned_user_id,
                assignedUserName: $lead->assignedUser?->name,
                daysIdle: max(0, (int) $lead->created_at?->diffInDays(now())),
                amountTier: null,
                stageName: null,
                hasOpenTask: isset($openTaskSubjectKeys[$subjectKey]),
                facts: ['وضعیت: جدید', 'تماس تکمیل‌شده ثبت نشده است'],
            );
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function idleOpportunities(
        User $user,
        int $idleDays,
        array $openTaskSubjectKeys,
        float $highAmount,
        int $nearCloseDays,
    ): array {
        $cutoff = now()->subDays($idleDays);
        $nearCloseUntil = now()->addDays($nearCloseDays)->toDateString();

        $query = Opportunity::query()
            ->with(['assignedUser:id,name', 'party:id,name', 'stage:id,name'])
            ->where('status', 'open')
            ->where(function (Builder $q) use ($cutoff, $nearCloseUntil, $highAmount) {
                $q->where('updated_at', '<=', $cutoff)
                    ->orWhere(function (Builder $near) use ($nearCloseUntil) {
                        $near->whereNotNull('expected_close_date')
                            ->whereDate('expected_close_date', '<=', $nearCloseUntil);
                    })
                    ->orWhere('amount', '>=', $highAmount);
            })
            ->orderByDesc('amount')
            ->limit(40);
        $this->scope->applyOwnerScope($query, $user);

        $out = [];
        foreach ($query->get() as $opp) {
            $lastActivityAt = Activity::query()
                ->where(function (Builder $q) use ($opp) {
                    $q->where(function (Builder $related) use ($opp) {
                        $related->where('activitable_type', Opportunity::class)
                            ->where('activitable_id', $opp->id);
                    })->orWhere('party_id', $opp->party_id);
                })
                ->where('status', 'completed')
                ->max('completed_at');

            $lastTouch = collect([$opp->updated_at, $lastActivityAt ? Carbon::parse($lastActivityAt) : null])
                ->filter()
                ->max();

            $daysIdle = $lastTouch ? max(0, (int) Carbon::parse($lastTouch)->diffInDays(now())) : $idleDays;
            $subjectKey = $this->subjectKey(Opportunity::class, $opp->id);
            $amountTier = $this->amountTier((float) $opp->amount, $highAmount);

            $kind = 'opportunity_idle';
            if ($opp->expected_close_date && $opp->expected_close_date->toDateString() <= $nearCloseUntil) {
                $kind = 'near_close_opportunity';
            } elseif ((float) $opp->amount >= $highAmount) {
                $kind = 'high_value_opportunity';
            }

            if ($kind === 'opportunity_idle' && $daysIdle < $idleDays) {
                continue;
            }

            $facts = [
                'مرحله: '.($opp->stage?->name ?? 'نامشخص'),
                'روزهای بدون پیگیری: '.$daysIdle,
                'سطح مبلغ: '.$amountTier,
            ];
            if ($opp->expected_close_date) {
                $facts[] = 'موعد تقریبی بستن: '.$opp->expected_close_date->toDateString();
            }

            $out[] = $this->candidate(
                key: 'opportunity:'.$opp->id,
                kind: $kind,
                subjectType: Opportunity::class,
                subjectId: $opp->id,
                partyId: $opp->party_id,
                partyName: $opp->party?->name,
                subjectLabel: $opp->number ?: $opp->title,
                assignedUserId: $opp->assigned_user_id,
                assignedUserName: $opp->assignedUser?->name,
                daysIdle: $daysIdle,
                amountTier: $amountTier,
                stageName: $opp->stage?->name,
                hasOpenTask: isset($openTaskSubjectKeys[$subjectKey]),
                facts: $facts,
            );
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function idleProformas(User $user, int $idleDays, array $openTaskSubjectKeys): array
    {
        $cutoff = now()->subDays($idleDays)->toDateString();

        $oppQuery = Opportunity::query()
            ->where('status', 'open')
            ->whereNotNull('invoice_id');
        $this->scope->applyOwnerScope($oppQuery, $user);
        $opportunityIdsByInvoice = $oppQuery->get(['id', 'invoice_id', 'assigned_user_id', 'party_id', 'number', 'title'])
            ->keyBy('invoice_id');

        if ($opportunityIdsByInvoice->isEmpty()) {
            return [];
        }

        $invoices = Invoice::query()
            ->with('party:id,name')
            ->whereIn('id', $opportunityIdsByInvoice->keys())
            ->where('direction', 'sale')
            ->where('document_type', 'proforma')
            ->whereIn('status', ['draft', 'confirmed'])
            ->whereDate('invoice_date', '<=', $cutoff)
            ->orderBy('invoice_date')
            ->limit(30)
            ->get();

        $out = [];
        foreach ($invoices as $invoice) {
            $opp = $opportunityIdsByInvoice->get($invoice->id);
            if (! $opp) {
                continue;
            }

            $subjectKey = $this->subjectKey(Opportunity::class, (int) $opp->id);
            $daysIdle = max(0, (int) Carbon::parse($invoice->invoice_date)->diffInDays(now()));

            $out[] = $this->candidate(
                key: 'proforma:'.$invoice->id,
                kind: 'proforma_idle',
                subjectType: Opportunity::class,
                subjectId: (int) $opp->id,
                partyId: $opp->party_id ?: $invoice->party_id,
                partyName: $invoice->party?->name,
                subjectLabel: ($invoice->number ?: ('پیش‌فاکتور #'.$invoice->id)).' / '.($opp->number ?: $opp->title),
                assignedUserId: $opp->assigned_user_id,
                assignedUserName: User::query()->whereKey($opp->assigned_user_id)->value('name'),
                daysIdle: $daysIdle,
                amountTier: $this->amountTier((float) $invoice->total_amount, (float) config('crm_ai.agents.sales_task_planner.high_amount_threshold', 500_000_000)),
                stageName: null,
                hasOpenTask: isset($openTaskSubjectKeys[$subjectKey]),
                facts: [
                    'پیش‌فاکتور باز است',
                    'تاریخ سند: '.$invoice->invoice_date,
                    'وضعیت سند: '.$invoice->status,
                ],
            );
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function overdueTasks(User $user): array
    {
        $query = Task::query()
            ->with(['assignedUser:id,name', 'party:id,name'])
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->orderBy('due_at')
            ->limit(30);
        $this->scope->applyOwnerScope($query, $user);

        $out = [];
        foreach ($query->get() as $task) {
            $daysIdle = max(0, (int) $task->due_at->diffInDays(now()));
            $out[] = $this->candidate(
                key: 'task:'.$task->id,
                kind: 'overdue_task',
                subjectType: Task::class,
                subjectId: $task->id,
                partyId: $task->party_id,
                partyName: $task->party?->name,
                subjectLabel: $task->title,
                assignedUserId: $task->assigned_user_id,
                assignedUserName: $task->assignedUser?->name,
                daysIdle: $daysIdle,
                amountTier: null,
                stageName: null,
                hasOpenTask: true,
                facts: [
                    'وظیفه عقب‌افتاده',
                    'موعد: '.$task->due_at->toDateTimeString(),
                    'اولویت فعلی: '.$task->priority,
                ],
            );
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function dormantCustomers(User $user, int $idleDays, array $openTaskPartyIds): array
    {
        $cutoff = now()->subDays($idleDays);

        $partyQuery = Party::query()
            ->whereHas('crmProfile', function (Builder $q) use ($user) {
                $q->whereIn('status', ['active', 'vip', 'prospect']);
                if (! $this->scope->canViewAll($user)) {
                    $q->where(function (Builder $owner) use ($user) {
                        $owner->where('assigned_user_id', $user->id)
                            ->orWhere('created_by', $user->id);
                    });
                }
            })
            ->with(['crmProfile.assignedUser:id,name'])
            ->limit(40);

        $out = [];
        foreach ($partyQuery->get() as $party) {
            $lastActivity = Activity::query()
                ->where('party_id', $party->id)
                ->where('status', 'completed')
                ->max('completed_at');

            $lastTouch = $lastActivity
                ? Carbon::parse($lastActivity)
                : ($party->crmProfile?->last_activity_at ?: $party->created_at);
            if ($lastTouch && $lastTouch->gt($cutoff)) {
                continue;
            }

            $hasOpenOpp = Opportunity::query()
                ->where('party_id', $party->id)
                ->where('status', 'open')
                ->exists();

            // Prefer opportunity/proforma candidates when an open deal exists.
            if ($hasOpenOpp) {
                continue;
            }

            $daysIdle = $lastTouch ? max(0, (int) $lastTouch->diffInDays(now())) : $idleDays;
            $ownerId = $party->crmProfile?->assigned_user_id;

            $out[] = $this->candidate(
                key: 'party:'.$party->id,
                kind: 'dormant_customer',
                subjectType: Party::class,
                subjectId: $party->id,
                partyId: $party->id,
                partyName: $party->name,
                subjectLabel: $party->name,
                assignedUserId: $ownerId,
                assignedUserName: $party->crmProfile?->assignedUser?->name,
                daysIdle: $daysIdle,
                amountTier: null,
                stageName: null,
                hasOpenTask: isset($openTaskPartyIds[$party->id]),
                facts: [
                    'وضعیت مشتری: '.($party->crmProfile?->status ?? 'نامشخص'),
                    'آخرین تماس تکمیل‌شده: '.($lastActivity ?: 'ثبت نشده'),
                ],
            );
        }

        return $out;
    }

    /**
     * @param  list<string>  $facts
     * @return array<string, mixed>
     */
    private function candidate(
        string $key,
        string $kind,
        ?string $subjectType,
        ?int $subjectId,
        ?int $partyId,
        ?string $partyName,
        string $subjectLabel,
        ?int $assignedUserId,
        ?string $assignedUserName,
        int $daysIdle,
        ?string $amountTier,
        ?string $stageName,
        bool $hasOpenTask,
        array $facts,
    ): array {
        return [
            'key' => $key,
            'kind' => $kind,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'party_id' => $partyId,
            'party_name' => $partyName,
            'subject_label' => $subjectLabel,
            'assigned_user_id' => $assignedUserId,
            'assigned_user_name' => $assignedUserName,
            'days_idle' => $daysIdle,
            'amount_tier' => $amountTier,
            'stage_name' => $stageName,
            'has_open_task' => $hasOpenTask,
            'facts' => $facts,
        ];
    }

    private function subjectKey(?string $type, int $id): string
    {
        return ($type ?? 'unknown').':'.$id;
    }

    private function amountTier(float $amount, float $highThreshold): string
    {
        if ($amount >= $highThreshold) {
            return 'high';
        }
        if ($amount >= $highThreshold / 5) {
            return 'medium';
        }

        return $amount > 0 ? 'low' : 'none';
    }
}
