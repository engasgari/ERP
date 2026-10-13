<?php

namespace App\Repositories\Crm;

use App\Models\Crm\Activity;
use App\Models\Crm\Opportunity;
use App\Models\Crm\Task;
use App\Models\Invoice;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-only queries backing the deterministic customer summary. All scoped to one party_id.
 */
class CrmCustomerInsightRepository
{
    /**
     * @return Collection<int, Activity>
     */
    public function recentCompletedActivities(int $partyId, int $limit = 5): Collection
    {
        return Activity::query()
            ->where('party_id', $partyId)
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->latest('completed_at')
            ->limit($limit)
            ->get(['id', 'type', 'subject', 'completed_at']);
    }

    public function lastCompletedCall(int $partyId): ?Activity
    {
        return Activity::query()
            ->where('party_id', $partyId)
            ->where('type', 'call')
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->latest('completed_at')
            ->first(['id', 'subject', 'completed_at']);
    }

    public function completedCallsSince(int $partyId, DateTimeInterface $since): int
    {
        return Activity::query()
            ->where('party_id', $partyId)
            ->where('type', 'call')
            ->where('status', 'completed')
            ->where('completed_at', '>=', $since)
            ->count();
    }

    /**
     * @return array{open: int, overdue: int, next: ?Task, last_completed_at: ?Carbon}
     */
    public function taskStats(int $partyId): array
    {
        $open = Task::query()->where('party_id', $partyId)->whereIn('status', ['pending', 'in_progress']);

        $lastCompleted = Task::query()
            ->where('party_id', $partyId)
            ->where('status', 'completed')
            ->max('completed_at');

        return [
            'open' => (clone $open)->count(),
            'overdue' => (clone $open)->whereNotNull('due_at')->where('due_at', '<', now())->count(),
            'next' => (clone $open)->whereNotNull('due_at')->orderBy('due_at')->first(['id', 'title', 'due_at']),
            'last_completed_at' => $lastCompleted ? Carbon::parse($lastCompleted) : null,
        ];
    }

    /**
     * @return Collection<int, Opportunity>
     */
    public function openOpportunities(int $partyId): Collection
    {
        return Opportunity::query()
            ->where('party_id', $partyId)
            ->where('status', 'open')
            ->with('stage:id,name')
            ->orderByDesc('amount')
            ->get(['id', 'number', 'title', 'stage_id', 'amount', 'expected_close_date', 'updated_at']);
    }

    /**
     * @return array{count: int, total: string, latest: ?Invoice}
     */
    public function saleDocumentStats(int $partyId, string $documentType, array $statuses): array
    {
        $query = Invoice::query()
            ->where('party_id', $partyId)
            ->where('direction', 'sale')
            ->where('document_type', $documentType)
            ->whereIn('status', $statuses);

        return [
            'count' => (clone $query)->count(),
            'total' => (string) ((clone $query)->sum('total_amount') ?: '0'),
            'latest' => (clone $query)->latest('invoice_date')->latest('id')
                ->first(['id', 'number', 'invoice_date', 'status', 'total_amount']),
        ];
    }
}
