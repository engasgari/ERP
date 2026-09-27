<?php

namespace App\Services;

use App\Models\AccountingAudit;
use App\Models\ChartAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ChartOfAccountsStandardizationService
{
    public const AUDIT_EVENT = 'coa_standardization';

    /**
     * Ordered operations. Parents are created before children and children are deleted before parents.
     *
     * @return list<array<string, mixed>>
     */
    public function operations(): array
    {
        return [
            ['action' => 'CREATE', 'code' => '1203', 'title' => 'موجودی نقد ارزی', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '12', 'reason' => 'ساختار واحد موجودی ارزی زیر نقد و بانک'],
            ['action' => 'CREATE', 'code' => '120301', 'title' => 'دلار آمریکا', 'level' => 'detail', 'nature' => 'debit', 'parent' => '1203', 'reason' => 'ارز دلار'],
            ['action' => 'CREATE', 'code' => '120302', 'title' => 'یورو', 'level' => 'detail', 'nature' => 'debit', 'parent' => '1203', 'reason' => 'ارز یورو'],
            ['action' => 'CREATE', 'code' => '120303', 'title' => 'درهم امارات', 'level' => 'detail', 'nature' => 'debit', 'parent' => '1203', 'reason' => 'ارز درهم'],
            ['action' => 'CREATE', 'code' => '13', 'title' => 'دارایی‌های ثابت مشهود', 'level' => 'ledger', 'nature' => 'debit', 'parent' => '1', 'reason' => 'خودروهای خریداری‌شده برای مدیریت'],
            ['action' => 'CREATE', 'code' => '1301', 'title' => 'وسائط نقلیه', 'level' => 'subsidiary', 'nature' => 'debit', 'parent' => '13', 'reason' => 'بهای تمام‌شده خودروها'],
            ['action' => 'CREATE', 'code' => '130101', 'title' => 'خودروی کوئیک', 'level' => 'detail', 'nature' => 'debit', 'parent' => '1301', 'reason' => 'تفصیلی مستقل هر خودرو'],
            ['action' => 'CREATE', 'code' => '130102', 'title' => 'خودرو ۲۰۶ تیپ ۳ مشکی', 'level' => 'detail', 'nature' => 'debit', 'parent' => '1301', 'reason' => 'تفصیلی مستقل هر خودرو'],
            ['action' => 'CREATE', 'code' => '1302', 'title' => 'استهلاک انباشته وسائط نقلیه', 'level' => 'subsidiary', 'nature' => 'credit', 'parent' => '13', 'reason' => 'حساب کاهنده دارایی'],
            ['action' => 'CREATE', 'code' => '130201', 'title' => 'استهلاک انباشته خودروی کوئیک', 'level' => 'detail', 'nature' => 'credit', 'parent' => '1302', 'reason' => 'استهلاک انباشته هر خودرو'],
            ['action' => 'CREATE', 'code' => '130202', 'title' => 'استهلاک انباشته خودرو ۲۰۶ تیپ ۳ مشکی', 'level' => 'detail', 'nature' => 'credit', 'parent' => '1302', 'reason' => 'استهلاک انباشته هر خودرو'],

            ['action' => 'RENAME', 'code' => '1111', 'title' => 'سرمایه‌گذاری‌های کوتاه‌مدت', 'reason' => 'طلا و سکه برای سرمایه‌گذاری نگهداری می‌شوند'],
            ['action' => 'RENAME', 'code' => '111103', 'title' => 'صندوق‌های سرمایه‌گذاری', 'reason' => 'رفع ابهام با 1201 صندوق نقدی'],
            ['action' => 'RENAME', 'code' => '4102', 'title' => 'سایر درآمدهای عملیاتی', 'reason' => 'عنوان تکراری با تفصیلی 410201'],
            ['action' => 'RENAME', 'code' => '520103', 'title' => 'هزینه‌های تنخواه', 'reason' => 'تفکیک از 110901 تنخواه (دارایی)'],
            ['action' => 'RENAME', 'code' => '2015', 'new_code' => '2105', 'reason' => 'کد معین با زیرحساب‌های 2105xx هماهنگ شود'],
            ['action' => 'RELEVEL', 'code' => '110702', 'level' => 'detail', 'reason' => 'زیر معین 1107 باید تفصیلی باشد'],
            ['action' => 'MOVE', 'code' => '12-5009', 'parent' => '1202', 'new_code' => '1202-5009', 'reason' => 'تفصیلی بانک زیر معین بانک؛ 154 ردیف قبلاً با 1202 ثبت شده‌اند'],
            ['action' => 'REBIND_BANKS', 'code' => '12', 'target' => '1202', 'reason' => 'حساب معین بانک‌های 5006 و 5009 به جای کل 12'],

            ['action' => 'DEPRECATE', 'code' => '520109', 'title' => 'کارمزد بانکی (قدیمی)', 'target' => '520601', 'reason' => 'همه ردیف‌ها کارمزد بانکی است؛ ثبت جدید در 520601'],
            ['action' => 'DEPRECATE', 'code' => '520307', 'target' => '520104', 'reason' => 'تکراری پذیرایی'],
            ['action' => 'DEPRECATE', 'code' => '210501', 'target' => '2102', 'reason' => 'تکراری مالیات ارزش افزوده پرداختنی'],

            ['action' => 'DELETE', 'code' => '520301', 'target' => '520101', 'reason' => 'تکراری اجاره'],
            ['action' => 'DELETE', 'code' => '520305', 'target' => '520114', 'reason' => 'تکراری اینترنت'],
            ['action' => 'DELETE', 'code' => '520308', 'target' => '520102', 'reason' => 'هم‌معنی ناهار پرسنل'],
            ['action' => 'DELETE', 'code' => '520310', 'target' => '110901', 'reason' => 'تنخواه دارایی است نه هزینه'],
            ['action' => 'DELETE', 'code' => '520502', 'target' => '520112', 'reason' => 'تکراری هزینه سایت'],
            ['action' => 'DELETE', 'code' => '110701', 'target' => '110801', 'reason' => 'تکراری سپرده بیمه'],
            ['action' => 'DELETE', 'code' => '110905', 'target' => '32', 'reason' => 'هم‌پوشانی با حساب جاری شرکا'],
            ['action' => 'DELETE', 'code' => '1104', 'target' => '1101', 'reason' => 'مانده اول دوره در همان حساب دریافتنی ثبت می‌شود'],
            ['action' => 'DELETE', 'code' => '2103', 'target' => '2101', 'reason' => 'مانده اول دوره در همان حساب پرداختنی ثبت می‌شود'],
            ['action' => 'DELETE', 'code' => '111001', 'target' => '120301', 'reason' => 'ساختار ارزی تکراری'],
            ['action' => 'DELETE', 'code' => '111002', 'target' => '120302', 'reason' => 'ساختار ارزی تکراری'],
            ['action' => 'DELETE', 'code' => '111003', 'target' => '120303', 'reason' => 'ساختار ارزی تکراری'],
            ['action' => 'DELETE', 'code' => '1110', 'target' => '1203', 'reason' => 'ساختار ارزی تکراری'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function plan(): array
    {
        return array_map(fn (array $operation) => $this->evaluate($operation), $this->operations());
    }

    /**
     * @return array{applied: list<array<string, mixed>>, validation: array<string, mixed>, backup: ?string}
     */
    public function execute(?int $userId = null, bool $backup = true): array
    {
        $backupTable = $backup ? $this->backup() : null;

        return DB::transaction(function () use ($userId, $backupTable) {
            $before = $this->ledgerSnapshot();
            $applied = [];

            foreach ($this->operations() as $operation) {
                $evaluated = $this->evaluate($operation);

                if ($evaluated['status'] !== 'pending') {
                    $applied[] = $evaluated;

                    continue;
                }

                $applied[] = $this->apply($evaluated, $userId);
            }

            $after = $this->ledgerSnapshot();

            if ($before !== $after) {
                throw new RuntimeException('مانده یا گردش حساب‌ها پس از اصلاح کدینگ تغییر کرد؛ عملیات برگشت داده شد.');
            }

            $structure = $this->structuralIssues();

            if ($structure['blocking'] !== []) {
                throw new RuntimeException('ساختار حساب‌ها پس از اصلاح نامعتبر است: '.implode(' | ', $structure['blocking']));
            }

            return [
                'applied' => $applied,
                'validation' => [
                    'ledger_unchanged' => true,
                    'line_count' => $after['line_count'],
                    'debit_total' => $after['debit_total'],
                    'credit_total' => $after['credit_total'],
                    'warnings' => $structure['warnings'],
                ],
                'backup' => $backupTable,
            ];
        });
    }

    /**
     * @return array{blocking: list<string>, warnings: list<string>}
     */
    public function structuralIssues(): array
    {
        $accounts = ChartAccount::query()->get(['id', 'parent_id', 'code', 'title', 'level', 'is_active'])->keyBy('id');
        $blocking = [];
        $warnings = [];

        foreach ($accounts as $account) {
            if ($account->parent_id && ! $accounts->has($account->parent_id)) {
                $blocking[] = "حساب {$account->code} والد ناموجود دارد.";
            }

            $seen = [];
            $cursor = $account;
            while ($cursor && $cursor->parent_id) {
                if (isset($seen[$cursor->id])) {
                    $blocking[] = "حلقه در ساختار والد حساب {$account->code}.";
                    break;
                }
                $seen[$cursor->id] = true;
                $cursor = $accounts->get($cursor->parent_id);
            }

            $parent = $account->parent_id ? $accounts->get($account->parent_id) : null;
            if ($parent && $parent->level === 'detail') {
                $warnings[] = "تفصیلی زیر تفصیلی: {$account->code} زیر {$parent->code}";
            }
            if ($account->level === 'detail' && $parent && $parent->level !== 'subsidiary') {
                $warnings[] = "تفصیلی {$account->code} مستقیماً زیر {$parent->level} {$parent->code} است.";
            }
        }

        $orphanLines = DB::table('accounting_document_lines as l')
            ->leftJoin('chart_accounts as a', 'a.id', '=', 'l.chart_account_id')
            ->whereNull('a.id')
            ->count();
        if ($orphanLines > 0) {
            $blocking[] = "{$orphanLines} ردیف سند به حساب ناموجود اشاره می‌کند.";
        }

        $orphanDetails = DB::table('accounting_document_lines as l')
            ->leftJoin('chart_accounts as a', 'a.id', '=', 'l.detail_account_id')
            ->whereNotNull('l.detail_account_id')
            ->whereNull('a.id')
            ->count();
        if ($orphanDetails > 0) {
            $blocking[] = "{$orphanDetails} ردیف سند به تفصیلی ناموجود اشاره می‌کند.";
        }

        $accounts->where('is_active', true)
            ->groupBy(fn ($a) => $a->parent_id.'|'.$this->normalizeTitle($a->title))
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->each(function (Collection $group) use (&$warnings) {
                $warnings[] = 'حساب فعال تکراری زیر یک والد: '.$group->pluck('code')->implode('، ');
            });

        return ['blocking' => $blocking, 'warnings' => $warnings];
    }

    /**
     * Per-account debit/credit totals by id; identical before and after means no document changed meaning.
     *
     * @return array<string, mixed>
     */
    public function ledgerSnapshot(): array
    {
        $byColumn = [];
        foreach (['chart_account_id', 'detail_account_id'] as $column) {
            $byColumn[$column] = DB::table('accounting_document_lines')
                ->whereNotNull($column)
                ->select($column.' as account_id', DB::raw('count(*) as n'), DB::raw('sum(debit) as dr'), DB::raw('sum(credit) as cr'))
                ->groupBy($column)
                ->orderBy($column)
                ->get()
                ->mapWithKeys(fn ($row) => [(int) $row->account_id => [(int) $row->n, (string) $row->dr, (string) $row->cr]])
                ->all();
        }

        $totals = DB::table('accounting_document_lines')
            ->select(DB::raw('count(*) as n'), DB::raw('coalesce(sum(debit),0) as dr'), DB::raw('coalesce(sum(credit),0) as cr'))
            ->first();

        return [
            'by_account' => $byColumn['chart_account_id'],
            'by_detail' => $byColumn['detail_account_id'],
            'line_count' => (int) $totals->n,
            'debit_total' => (string) $totals->dr,
            'credit_total' => (string) $totals->cr,
        ];
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function evaluate(array $operation): array
    {
        $account = ChartAccount::query()->where('code', $operation['code'])->first();
        $usage = $account ? $this->usage($account) : ['posted_lines' => 0, 'other_lines' => 0, 'refs' => [], 'children' => 0, 'balance' => 0.0];
        $result = $operation + ['account_id' => $account?->id, 'usage' => $usage, 'status' => 'pending', 'note' => null];

        switch ($operation['action']) {
            case 'CREATE':
                if ($account) {
                    $result['status'] = 'done';
                } elseif (! ChartAccount::query()->where('code', $operation['parent'])->exists()) {
                    $result['status'] = 'pending';
                    $result['note'] = 'والد در همین اجرا ساخته می‌شود';
                }
                break;

            case 'RENAME':
                if (isset($operation['new_code'])) {
                    $target = ChartAccount::query()->where('code', $operation['new_code'])->first();
                    if (! $account && $target) {
                        $result['status'] = 'done';
                    } elseif ($account && $target) {
                        $result['status'] = 'blocked';
                        $result['note'] = "کد {$operation['new_code']} قبلاً وجود دارد";
                    } elseif (! $account) {
                        $result['status'] = 'skipped';
                        $result['note'] = 'حساب وجود ندارد';
                    }
                } elseif (! $account) {
                    $result['status'] = 'skipped';
                    $result['note'] = 'حساب وجود ندارد';
                } elseif ($account->title === $operation['title']) {
                    $result['status'] = 'done';
                }
                break;

            case 'RELEVEL':
                if (! $account) {
                    $result['status'] = 'skipped';
                } elseif ($account->level === $operation['level']) {
                    $result['status'] = 'done';
                } elseif ($account->children()->exists()) {
                    $result['status'] = 'blocked';
                    $result['note'] = 'حساب زیرمجموعه دارد';
                }
                break;

            case 'MOVE':
                $parent = ChartAccount::query()->where('code', $operation['parent'])->first();
                $moved = ChartAccount::query()->where('code', $operation['new_code'])->first();
                if (! $account && $moved && $parent && (int) $moved->parent_id === (int) $parent->id) {
                    $result['status'] = 'done';
                } elseif (! $account) {
                    $result['status'] = 'skipped';
                    $result['note'] = 'حساب وجود ندارد';
                } elseif (! $parent) {
                    $result['status'] = 'blocked';
                    $result['note'] = 'والد مقصد وجود ندارد';
                } elseif ($moved) {
                    $result['status'] = 'blocked';
                    $result['note'] = "کد {$operation['new_code']} قبلاً وجود دارد";
                }
                break;

            case 'REBIND_BANKS':
                $pending = $account ? DB::table('bank_accounts')->where('chart_account_id', $account->id)->count() : 0;
                $result['note'] = "{$pending} حساب بانکی";
                if ($pending === 0) {
                    $result['status'] = 'done';
                } elseif (! ChartAccount::query()->where('code', $operation['target'])->exists()) {
                    $result['status'] = 'blocked';
                    $result['note'] = 'حساب مقصد وجود ندارد';
                }
                break;

            case 'DEPRECATE':
                if (! $account) {
                    $result['status'] = 'skipped';
                    $result['note'] = 'حساب وجود ندارد';
                } elseif (! $account->is_active && (! isset($operation['title']) || $account->title === $operation['title'])) {
                    $result['status'] = 'done';
                }
                break;

            case 'DELETE':
                if (! $account) {
                    $result['status'] = 'done';
                } elseif (! $this->isDeletable($account, $usage)) {
                    $result['action'] = 'DEPRECATE';
                    $result['note'] = 'استفاده دارد؛ به جای حذف غیرفعال می‌شود';
                    $result['status'] = $account->is_active ? 'pending' : 'done';
                }
                break;
        }

        return $result;
    }

    /**
     * Children that are themselves unused and scheduled for deletion do not block the parent.
     *
     * @param  array<string, mixed>  $usage
     */
    private function isDeletable(ChartAccount $account, array $usage): bool
    {
        if ($usage['posted_lines'] + $usage['other_lines'] > 0 || $usage['refs'] !== []) {
            return false;
        }

        $scheduled = collect($this->operations())->where('action', 'DELETE')->pluck('code')->all();

        foreach (ChartAccount::query()->where('parent_id', $account->id)->get() as $child) {
            if (! in_array($child->code, $scheduled, true) || ! $this->isDeletable($child, $this->usage($child))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function apply(array $operation, ?int $userId): array
    {
        $account = $operation['account_id'] ? ChartAccount::query()->find($operation['account_id']) : null;
        $old = $account?->only(['code', 'title', 'level', 'nature', 'parent_id', 'is_active', 'is_system']);

        switch ($operation['action']) {
            case 'CREATE':
                $parentId = ChartAccount::query()->where('code', $operation['parent'])->value('id');
                if (! $parentId) {
                    throw new RuntimeException("والد {$operation['parent']} برای حساب {$operation['code']} پیدا نشد.");
                }
                $account = ChartAccount::query()->create([
                    'parent_id' => $parentId,
                    'level' => $operation['level'],
                    'code' => $operation['code'],
                    'title' => $operation['title'],
                    'nature' => $operation['nature'],
                    'is_active' => true,
                    'is_system' => true,
                ]);
                break;

            case 'RENAME':
                $account->update(isset($operation['new_code']) ? ['code' => $operation['new_code']] : ['title' => $operation['title']]);
                break;

            case 'RELEVEL':
                $account->update(['level' => $operation['level']]);
                break;

            case 'MOVE':
                $account->update([
                    'parent_id' => ChartAccount::query()->where('code', $operation['parent'])->value('id'),
                    'code' => $operation['new_code'],
                ]);
                break;

            case 'REBIND_BANKS':
                $targetId = ChartAccount::query()->where('code', $operation['target'])->value('id');
                $banks = DB::table('bank_accounts')->where('chart_account_id', $account->id)->get(['id', 'code', 'chart_account_id']);
                DB::table('bank_accounts')->whereIn('id', $banks->pluck('id'))->update(['chart_account_id' => $targetId, 'updated_at' => now()]);
                $old = ['bank_accounts' => $banks->map(fn ($b) => ['id' => $b->id, 'code' => $b->code, 'chart_account_id' => $b->chart_account_id])->all()];
                break;

            case 'DEPRECATE':
                $account->update(array_filter([
                    'is_active' => false,
                    'title' => $operation['title'] ?? null,
                ], fn ($value) => $value !== null));
                break;

            case 'DELETE':
                $usage = $this->usage($account);
                if ($usage['posted_lines'] + $usage['other_lines'] > 0 || $usage['refs'] !== [] || $usage['children'] > 0) {
                    $account->update(['is_active' => false]);
                    $operation['action'] = 'DEPRECATE';
                    $operation['note'] = 'وابستگی داشت؛ به جای حذف غیرفعال شد';
                    break;
                }
                $account->delete();
                break;
        }

        AccountingAudit::query()->create([
            'auditable_type' => ChartAccount::class,
            'auditable_id' => $account?->id ?? $operation['account_id'] ?? 0,
            'event' => self::AUDIT_EVENT.'.'.strtolower($operation['action']),
            'user_id' => $userId,
            'old_values' => $old,
            'new_values' => [
                'operation' => collect($operation)->except(['usage', 'status', 'account_id'])->all(),
                'account' => $operation['action'] === 'DELETE' ? null : $account?->fresh()?->only(['code', 'title', 'level', 'nature', 'parent_id', 'is_active']),
            ],
        ]);

        return array_merge($operation, ['status' => 'applied']);
    }

    /**
     * @return array{posted_lines: int, other_lines: int, refs: array<string, int>, children: int, balance: float}
     */
    private function usage(ChartAccount $account): array
    {
        $lines = DB::table('accounting_document_lines as l')
            ->join('accounting_documents as d', 'd.id', '=', 'l.accounting_document_id')
            ->where(fn ($q) => $q->where('l.chart_account_id', $account->id)->orWhere('l.detail_account_id', $account->id))
            ->select(
                DB::raw("sum(case when d.status = 'posted' then 1 else 0 end) as posted"),
                DB::raw("sum(case when d.status <> 'posted' then 1 else 0 end) as other"),
                DB::raw("coalesce(sum(case when d.status = 'posted' then l.debit - l.credit else 0 end), 0) as balance")
            )
            ->first();

        $refs = [];
        foreach ($this->foreignKeyColumns() as [$table, $column]) {
            $count = DB::table($table)->where($column, $account->id)->count();
            if ($count > 0) {
                $refs["{$table}.{$column}"] = $count;
            }
        }

        return [
            'posted_lines' => (int) ($lines->posted ?? 0),
            'other_lines' => (int) ($lines->other ?? 0),
            'refs' => $refs,
            'children' => ChartAccount::query()->where('parent_id', $account->id)->count(),
            'balance' => round((float) ($lines->balance ?? 0), 2),
        ];
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function foreignKeyColumns(): array
    {
        static $columns = null;

        if ($columns === null) {
            $columns = collect(DB::select("
                SELECT TABLE_NAME AS t, COLUMN_NAME AS c
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE REFERENCED_TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = 'chart_accounts'
            "))
                ->reject(fn ($row) => $row->t === 'accounting_document_lines' || ($row->t === 'chart_accounts' && $row->c === 'parent_id'))
                ->map(fn ($row) => [$row->t, $row->c])
                ->values()
                ->all();
        }

        return $columns;
    }

    private function backup(): string
    {
        $suffix = now()->format('Ymd_His');
        $table = "chart_accounts_backup_{$suffix}";

        DB::statement("CREATE TABLE `{$table}` AS SELECT * FROM `chart_accounts`");
        DB::statement("CREATE TABLE `bank_accounts_coa_backup_{$suffix}` AS SELECT id, code, chart_account_id, detail_account_id FROM `bank_accounts`");

        if (DB::table($table)->count() !== ChartAccount::query()->count()) {
            throw new RuntimeException('نسخه پشتیبان حساب‌ها کامل نیست.');
        }

        return $table;
    }

    private function normalizeTitle(string $title): string
    {
        return preg_replace('/\s+/u', ' ', trim(str_replace(['ي', 'ك', "\u{200C}"], ['ی', 'ک', ' '], $title)));
    }
}
