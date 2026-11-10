<?php

namespace App\Livewire\ChartAccounts;

use App\Livewire\Core\UI\BaseListPage;
use App\Models\ChartAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class Index extends BaseListPage
{
    public string $search = '';

    public string $level = '';

    public string $nature = '';

    public string $is_system = '';

    public ?int $editingId = null;

    public string $edit_code = '';

    public string $edit_title = '';

    public string $edit_level = 'detail';

    public string $edit_nature = 'neutral';

    public string $edit_parent_id = '';

    public string $edit_is_active = '1';

    public array $levelLabels = [
        'group' => 'گروه',
        'ledger' => 'کل',
        'subsidiary' => 'معین',
        'detail' => 'تفصیل',
    ];

    public array $natureLabels = [
        'debit' => 'بدهکار',
        'credit' => 'بستانکار',
        'neutral' => 'خنثی',
    ];

    protected array $queryString = [
        'search' => ['except' => ''],
        'level' => ['except' => ''],
        'nature' => ['except' => ''],
        'is_system' => ['except' => ''],
    ];

    public function clearFilters(): void
    {
        $this->reset(['search', 'level', 'nature', 'is_system']);
    }

    public function edit(int $accountId): void
    {
        $account = ChartAccount::query()->findOrFail($accountId);

        $this->editingId = (int) $account->id;
        $this->edit_code = (string) $account->code;
        $this->edit_title = (string) $account->title;
        $this->edit_level = (string) $account->level;
        $this->edit_nature = (string) $account->nature;
        $this->edit_parent_id = $account->parent_id ? (string) $account->parent_id : '';
        $this->edit_is_active = $account->is_active ? '1' : '0';
        $this->resetValidation();
    }

    public function closeEditModal(): void
    {
        $this->editingId = null;
        $this->edit_code = '';
        $this->edit_title = '';
        $this->edit_level = 'detail';
        $this->edit_nature = 'neutral';
        $this->edit_parent_id = '';
        $this->edit_is_active = '1';
        $this->resetValidation();
    }

    public function saveEdit(): void
    {
        $account = ChartAccount::query()->findOrFail($this->editingId);

        $validated = $this->validate([
            'edit_code' => 'required|string|max:255|unique:chart_accounts,code,' . $account->id,
            'edit_title' => 'required|string|max:255',
            'edit_level' => 'required|in:group,ledger,subsidiary,detail',
            'edit_nature' => 'required|in:debit,credit,neutral',
            'edit_parent_id' => 'nullable|exists:chart_accounts,id',
            'edit_is_active' => 'required|in:0,1',
        ], [], [
            'edit_code' => 'کد',
            'edit_title' => 'عنوان',
            'edit_level' => 'سطح',
            'edit_nature' => 'ماهیت',
            'edit_parent_id' => 'والد',
            'edit_is_active' => 'وضعیت فعال',
        ]);

        if (! empty($validated['edit_parent_id']) && (int) $validated['edit_parent_id'] === (int) $account->id) {
            $this->addError('edit_parent_id', 'حساب نمی‌تواند والد خودش باشد.');

            return;
        }

        $willDeactivate = (string) $validated['edit_is_active'] === '0';
        if ($willDeactivate) {
            $usage = $this->usageStats()->get((int) $account->id);
            $hasTurnover = ((int) ($usage['document_count'] ?? 0) > 0) || ((float) ($usage['turnover'] ?? 0) > 0);
            if ($hasTurnover) {
                $this->addError('edit_is_active', 'حساب دارای گردش است و نمی‌تواند غیرفعال شود.');

                return;
            }
        }

        $account->update([
            'code' => $validated['edit_code'],
            'title' => $validated['edit_title'],
            'level' => $validated['edit_level'],
            'nature' => $validated['edit_nature'],
            'parent_id' => $validated['edit_parent_id'] !== '' && $validated['edit_parent_id'] !== null
                ? (int) $validated['edit_parent_id']
                : null,
            'is_active' => (string) $validated['edit_is_active'] === '1',
        ]);

        $this->closeEditModal();
        session()->flash('success', 'سرفصل مالی ویرایش شد.');
    }

    public function delete(int $accountId): void
    {
        $account = ChartAccount::with('children')->findOrFail($accountId);
        $usageByAccount = $this->usageStats();
        $blockReason = $this->deleteBlockReason($account, $usageByAccount);

        if ($blockReason !== null) {
            session()->flash('error', 'این سرفصل قابل حذف نیست.');
            session()->flash('error_details', [$blockReason]);

            return;
        }

        try {
            $deleted = $this->deleteAccountSubtree($account, $usageByAccount);
            session()->flash('success', $deleted > 1
                ? "{$deleted} سرفصل بدون گردش حذف شد."
                : 'سرفصل مالی حذف شد.');
        } catch (Throwable) {
            session()->flash('error', 'امکان حذف این سرفصل مالی وجود ندارد.');
            session()->flash('error_details', ['ممکن است سند حسابداری یا زیرمجموعه‌ای به این حساب وصل باشد.']);
        }
    }

    public function updateField(int $accountId, string $field, mixed $value): void
    {
        abort_unless(in_array($field, ['title', 'level', 'nature'], true), 403);

        $account = ChartAccount::findOrFail($accountId);
        $data = match ($field) {
            'title' => ['title' => trim((string) $value)],
            'level' => ['level' => array_key_exists((string) $value, $this->levelLabels) ? (string) $value : $account->level],
            'nature' => ['nature' => array_key_exists((string) $value, $this->natureLabels) ? (string) $value : $account->nature],
        };

        if (array_key_exists('title', $data) && $data['title'] === '') {
            session()->flash('error', 'عنوان سرفصل مالی الزامی است.');

            return;
        }

        $account->update($data);
        session()->flash('success', 'تغییرات سرفصل مالی ذخیره شد.');
    }

    public function render()
    {
        $accounts = $this->filteredAccounts();
        $usageByAccount = $this->usageStats();
        $tree = $this->buildTree($accounts, $usageByAccount);
        $expandedIds = $this->defaultExpandedIds($accounts, $tree);

        $parentOptions = ChartAccount::query()
            ->when($this->editingId, fn ($query) => $query->where('id', '!=', $this->editingId))
            ->orderBy('code')
            ->get(['id', 'code', 'title', 'level']);

        return view('livewire.chart-accounts.index', [
            'tree' => $tree,
            'expandedIds' => $expandedIds,
            'accountCount' => $accounts->count(),
            'parentOptions' => $parentOptions,
            'hasActiveFilters' => $this->hasActiveFilters(),
        ]);
    }

    private function filteredAccounts(): Collection
    {
        $all = $this->baseQuery()
            ->orderBy('chart_accounts.code')
            ->get();

        if (! $this->hasActiveFilters()) {
            return $all;
        }

        $matchedIds = $all
            ->filter(fn (ChartAccount $account) => $this->matchesFilters($account))
            ->pluck('id')
            ->all();

        if ($matchedIds === []) {
            return collect();
        }

        $byId = $all->keyBy('id');
        $keepIds = [];

        foreach ($matchedIds as $matchedId) {
            $currentId = $matchedId;

            while ($currentId && ! isset($keepIds[$currentId])) {
                $keepIds[$currentId] = true;
                $currentId = $byId->get($currentId)?->parent_id;
            }
        }

        return $all->filter(fn (ChartAccount $account) => isset($keepIds[$account->id]))->values();
    }

    private function matchesFilters(ChartAccount $account): bool
    {
        if ($this->level !== '' && $account->level !== $this->level) {
            return false;
        }

        if ($this->nature !== '' && $account->nature !== $this->nature) {
            return false;
        }

        if ($this->is_system !== '' && (bool) $account->is_system !== (bool) $this->is_system) {
            return false;
        }

        if ($this->search === '') {
            return true;
        }

        $search = mb_strtolower(trim($this->search));
        $haystack = mb_strtolower(implode(' ', array_filter([
            (string) $account->code,
            (string) $account->title,
            (string) ($account->parent_title ?? ''),
        ])));

        return str_contains($haystack, $search);
    }

    /**
     * @return Collection<int, array{document_count:int,line_count:int,turnover:float}>
     */
    private function usageStats(): Collection
    {
        $asChart = DB::table('accounting_document_lines')
            ->select('chart_account_id as account_id', 'accounting_document_id', 'debit', 'credit')
            ->whereNotNull('chart_account_id');

        $asDetail = DB::table('accounting_document_lines')
            ->select('detail_account_id as account_id', 'accounting_document_id', 'debit', 'credit')
            ->whereNotNull('detail_account_id');

        return DB::query()
            ->fromSub($asChart->unionAll($asDetail), 'account_usage')
            ->selectRaw('account_id, COUNT(DISTINCT accounting_document_id) as document_count, COUNT(*) as line_count, COALESCE(SUM(debit), 0) + COALESCE(SUM(credit), 0) as turnover')
            ->groupBy('account_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                (int) $row->account_id => [
                    'document_count' => (int) $row->document_count,
                    'line_count' => (int) $row->line_count,
                    'turnover' => (float) $row->turnover,
                ],
            ]);
    }

    /**
     * @param  Collection<int, array{document_count:int,line_count:int,turnover:float}>  $usageByAccount
     * @return list<array<string, mixed>>
     */
    private function buildTree(Collection $accounts, Collection $usageByAccount): array
    {
        $childrenMap = [];

        foreach ($accounts as $account) {
            $parentKey = $account->parent_id ?: 0;
            $childrenMap[$parentKey][] = $account;
        }

        $build = function (int $parentId) use (&$build, $childrenMap, $usageByAccount): array {
            $nodes = [];

            foreach ($childrenMap[$parentId] ?? [] as $account) {
                $children = $build((int) $account->id);
                $usage = $usageByAccount->get((int) $account->id, [
                    'document_count' => 0,
                    'line_count' => 0,
                    'turnover' => 0.0,
                ]);

                $ownDocumentCount = (int) $usage['document_count'];
                $ownTurnover = (float) $usage['turnover'];
                $childCount = count($children);

                // Roll up from children so group/ledger/subsidiary show the total of their subtree.
                $childrenDocumentCount = array_sum(array_map(
                    static fn (array $child): int => (int) $child['document_count'],
                    $children
                ));
                $childrenTurnover = array_sum(array_map(
                    static fn (array $child): float => (float) $child['turnover'],
                    $children
                ));

                $documentCount = $ownDocumentCount + $childrenDocumentCount;
                $turnover = $ownTurnover + $childrenTurnover;
                $hasTurnover = $documentCount > 0 || $turnover > 0;
                $subtreeHasSystem = (bool) $account->is_system || collect($children)->contains(
                    static fn (array $child): bool => (bool) ($child['subtree_has_system'] ?? $child['is_system'] ?? false)
                );

                $nodes[] = [
                    'id' => (int) $account->id,
                    'code' => (string) $account->code,
                    'title' => (string) $account->title,
                    'label' => chartAccountDisplayLabel($account),
                    'level' => (string) $account->level,
                    'level_label' => $this->levelLabels[$account->level] ?? $account->level,
                    'nature' => (string) $account->nature,
                    'nature_label' => $this->natureLabels[$account->nature] ?? $account->nature,
                    'is_system' => (bool) $account->is_system,
                    'is_active' => (bool) $account->is_active,
                    'subtree_has_system' => $subtreeHasSystem,
                    'children' => $children,
                    'child_count' => $childCount,
                    'own_document_count' => $ownDocumentCount,
                    'document_count' => $documentCount,
                    'turnover' => $turnover,
                    'has_turnover' => $hasTurnover,
                    // Any non-system account without turnover in its whole subtree can be deleted.
                    'can_delete' => ! $subtreeHasSystem && ! $hasTurnover,
                ];
            }

            return $nodes;
        };

        return $build(0);
    }

    /**
     * @param  Collection<int, array{document_count:int,line_count:int,turnover:float}>  $usageByAccount
     */
    private function deleteBlockReason(ChartAccount $account, Collection $usageByAccount): ?string
    {
        if ($account->is_system) {
            return 'این حساب سیستمی است.';
        }

        $descendantIds = $this->descendantIds($account);
        $allIds = array_merge([(int) $account->id], $descendantIds);

        $systemDescendant = ChartAccount::query()
            ->whereIn('id', $allIds)
            ->where('is_system', true)
            ->exists();

        if ($systemDescendant) {
            return 'در زیرمجموعه این سرفصل، حساب سیستمی وجود دارد.';
        }

        foreach ($allIds as $id) {
            $usage = $usageByAccount->get((int) $id, [
                'document_count' => 0,
                'line_count' => 0,
                'turnover' => 0.0,
            ]);

            if (((int) $usage['document_count']) > 0 || ((int) $usage['line_count']) > 0) {
                return 'این سرفصل یا زیرمجموعه‌هایش دارای گردش سند هستند.';
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, array{document_count:int,line_count:int,turnover:float}>  $usageByAccount
     */
    private function deleteAccountSubtree(ChartAccount $account, Collection $usageByAccount): int
    {
        $deleted = 0;

        $account->loadMissing('children');

        foreach ($account->children as $child) {
            $deleted += $this->deleteAccountSubtree($child, $usageByAccount);
        }

        $account->delete();

        return $deleted + 1;
    }

    /**
     * @return list<int>
     */
    private function descendantIds(ChartAccount $account): array
    {
        $ids = [];
        $queue = ChartAccount::query()
            ->where('parent_id', $account->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        while ($queue !== []) {
            $currentId = array_shift($queue);
            $ids[] = $currentId;
            $childIds = ChartAccount::query()
                ->where('parent_id', $currentId)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            foreach ($childIds as $childId) {
                $queue[] = $childId;
            }
        }

        return $ids;
    }

    /**
     * @param  list<array<string, mixed>>  $tree
     * @return list<int>
     */
    private function defaultExpandedIds(Collection $accounts, array $tree): array
    {
        if ($this->hasActiveFilters()) {
            return $accounts
                ->filter(fn (ChartAccount $account) => in_array($account->level, ['group', 'ledger', 'subsidiary'], true))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        // Start with groups collapsed; user expands with +.
        // Expand nothing by default for a clean overview of top-level groups only.
        return [];
    }

    private function hasActiveFilters(): bool
    {
        return $this->search !== ''
            || $this->level !== ''
            || $this->nature !== ''
            || $this->is_system !== '';
    }

    private function baseQuery()
    {
        return ChartAccount::query()
            ->leftJoin('chart_accounts as parent_accounts', 'parent_accounts.id', '=', 'chart_accounts.parent_id')
            ->select('chart_accounts.*', DB::raw('parent_accounts.title as parent_title'));
    }
}
