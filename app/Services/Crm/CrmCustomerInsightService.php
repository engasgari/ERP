<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmModel;
use App\Models\Party;
use App\Repositories\Crm\CrmCustomerInsightRepository;
use Illuminate\Support\Carbon;

/**
 * Deterministic internal CRM summary for one customer. Every number and date here comes from the
 * database; AI never produces these values.
 */
class CrmCustomerInsightService
{
    public const RELATIONSHIP_LABELS = [
        'no_contact' => 'بدون سابقه تماس',
        'dormant' => 'راکد',
        'needs_attention' => 'نیازمند پیگیری',
        'active_pipeline' => 'در حال مذاکره',
        'active' => 'فعال',
    ];

    public function __construct(private readonly CrmCustomerInsightRepository $insights) {}

    /**
     * @return array<string, mixed>
     */
    public function internalSummary(Party $party): array
    {
        $partyId = (int) $party->id;
        $recent = $this->insights->recentCompletedActivities($partyId);
        $lastCall = $this->insights->lastCompletedCall($partyId);
        $tasks = $this->insights->taskStats($partyId);
        $opportunities = $this->insights->openOpportunities($partyId);
        $proformas = $this->insights->saleDocumentStats($partyId, 'proforma', ['draft', 'confirmed']);
        $invoices = $this->insights->saleDocumentStats($partyId, 'invoice', ['confirmed']);

        $lastActivityAt = $recent->first()?->completed_at;
        $lastFollowUpAt = collect([$lastActivityAt, $tasks['last_completed_at']])->filter()->max();

        return [
            'last_activity' => $recent->first() ? [
                'type' => $recent->first()->type,
                'type_label' => CrmModel::ACTIVITY_TYPES[$recent->first()->type] ?? $recent->first()->type,
                'subject' => $recent->first()->subject,
                'completed_at' => $lastActivityAt,
            ] : null,
            'recent_activities' => $recent->map(fn ($a) => [
                'type' => $a->type,
                'type_label' => CrmModel::ACTIVITY_TYPES[$a->type] ?? $a->type,
                'subject' => $a->subject,
                'completed_at' => $a->completed_at,
            ])->all(),
            'last_call_at' => $lastCall?->completed_at,
            'calls_last_90_days' => $this->insights->completedCallsSince($partyId, now()->subDays(90)),
            'open_tasks' => $tasks['open'],
            'overdue_tasks' => $tasks['overdue'],
            'next_task' => $tasks['next'] ? ['title' => $tasks['next']->title, 'due_at' => $tasks['next']->due_at] : null,
            'open_opportunities' => [
                'count' => $opportunities->count(),
                'total_amount' => $this->sumDecimal($opportunities->pluck('amount')->all()),
                'items' => $opportunities->take(5)->map(fn ($o) => [
                    'number' => $o->number,
                    'title' => $o->title,
                    'stage' => $o->stage?->name,
                    'amount' => (string) $o->amount,
                    'expected_close_date' => $o->expected_close_date,
                ])->all(),
            ],
            'open_proformas' => [
                'count' => $proformas['count'],
                'total_amount' => $proformas['total'],
                'latest_number' => $proformas['latest']?->number,
                'latest_date' => $proformas['latest']?->invoice_date,
            ],
            'sale_invoices' => [
                'count' => $invoices['count'],
                'total_amount' => $invoices['total'],
                'latest_number' => $invoices['latest']?->number,
                'latest_date' => $invoices['latest']?->invoice_date,
            ],
            'last_follow_up_at' => $lastFollowUpAt,
            'relationship_status' => $status = $this->relationshipStatus($lastFollowUpAt, $tasks['overdue'], $opportunities->count()),
            'relationship_label' => self::RELATIONSHIP_LABELS[$status],
            'computed_at' => now(),
        ];
    }

    /**
     * Facts sent to the AI: counts, dates and statuses only. No amounts, phone numbers or people.
     *
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    public function toAiFacts(array $summary): array
    {
        $date = fn ($value) => $value instanceof Carbon ? $value->toDateString() : ($value ? Carbon::parse($value)->toDateString() : null);

        return [
            'relationship_status' => $summary['relationship_status'],
            'last_follow_up_date' => $date($summary['last_follow_up_at']),
            'last_call_date' => $date($summary['last_call_at']),
            'calls_last_90_days' => $summary['calls_last_90_days'],
            'recent_activities' => collect($summary['recent_activities'])->map(fn ($a) => [
                'type' => $a['type'],
                'date' => $date($a['completed_at']),
                'subject_untrusted' => mb_substr((string) $a['subject'], 0, 200),
            ])->all(),
            'open_tasks' => $summary['open_tasks'],
            'overdue_tasks' => $summary['overdue_tasks'],
            'next_task_due_date' => $date($summary['next_task']['due_at'] ?? null),
            'open_opportunities' => collect($summary['open_opportunities']['items'])->map(fn ($o) => [
                'stage' => $o['stage'],
                'expected_close_date' => $date($o['expected_close_date']),
            ])->all(),
            'open_opportunities_count' => $summary['open_opportunities']['count'],
            'open_proformas_count' => $summary['open_proformas']['count'],
            'sale_invoices_count' => $summary['sale_invoices']['count'],
            'last_invoice_date' => $date($summary['sale_invoices']['latest_date']),
            'today' => now()->toDateString(),
        ];
    }

    private function relationshipStatus(?Carbon $lastFollowUpAt, int $overdueTasks, int $openOpportunities): string
    {
        if ($lastFollowUpAt === null) {
            return $openOpportunities > 0 || $overdueTasks > 0 ? 'needs_attention' : 'no_contact';
        }

        if ($overdueTasks > 0) {
            return 'needs_attention';
        }

        $dormantDays = (int) config('crm_ai.agents.customer_intelligence.dormant_after_days', 90);
        if ($lastFollowUpAt->lt(now()->subDays($dormantDays))) {
            return 'dormant';
        }

        return $openOpportunities > 0 ? 'active_pipeline' : 'active';
    }

    /**
     * @param  list<mixed>  $amounts
     */
    private function sumDecimal(array $amounts): string
    {
        $total = '0';
        foreach ($amounts as $amount) {
            $total = bcadd($total, (string) ($amount ?? '0'), 2);
        }

        return $total;
    }
}
