<?php

namespace App\Services;

use App\Models\AccountingDocument;
use App\Models\ChartAccount;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\InventoryDocument;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class FiscalPeriodService
{
    public function __construct(private NumberingService $numbering)
    {
    }

    public function periodForDate(CarbonInterface|string $date): ?FiscalPeriod
    {
        $date = $this->normalizeDate($date);

        return FiscalPeriod::with('fiscalYear')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->orderByDesc('id')
            ->first();
    }

    public function getActiveFiscalPeriod(): ?FiscalPeriod
    {
        $today = now()->toDateString();

        $current = FiscalPeriod::with('fiscalYear')
            ->where('status', 'open')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->latest('id')
            ->first();

        if ($current) {
            return $current;
        }

        $active = FiscalPeriod::with('fiscalYear')
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->latest('id')
            ->first();

        if ($active) {
            return $active;
        }

        return FiscalPeriod::with('fiscalYear')
            ->where('status', 'open')
            ->latest('id')
            ->first();
    }

    public function isDateAllowed(CarbonInterface|string $date): bool
    {
        $period = $this->getActiveFiscalPeriod();
        if (! $period) {
            return false;
        }

        $date = $this->normalizeDate($date);

        return $date >= $period->start_date->toDateString() && $date <= $period->end_date->toDateString();
    }

    public function ensureDateIsAllowed(CarbonInterface|string $date): void
    {
        if (! $this->isDateAllowed($date)) {
            throw ValidationException::withMessages([
                'date' => 'تاریخ انتخاب‌شده خارج از بازه مالی فعال است.',
            ]);
        }
    }

    public function ensurePeriodIsOpen(?FiscalPeriod $period = null): void
    {
        $period ??= $this->getActiveFiscalPeriod();

        if (! $period || $period->status !== 'open') {
            throw ValidationException::withMessages([
                'date' => 'بازه مالی بسته است و امکان ثبت یا تغییر عملیات وجود ندارد.',
            ]);
        }
    }

    public function activatePeriod(FiscalPeriod $period): FiscalPeriod
    {
        DB::transaction(function () use ($period): void {
            FiscalPeriod::query()->update(['is_active' => false]);
            $period->update(['is_active' => true]);
        });

        return $period->refresh();
    }

    public function fiscalYearForDate(CarbonInterface|string $date): ?FiscalYear
    {
        $date = $this->normalizeDate($date);

        return FiscalYear::whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->orderByDesc('id')
            ->first();
    }

    public function assertOpen(CarbonInterface|string $date): void
    {
        $date = $this->normalizeDate($date);
        $this->ensureDateIsAllowed($date);
        $period = $this->periodForDate($date);

        if ($period && $period->status === 'closed') {
            throw ValidationException::withMessages([
                'date' => 'بازه مالی بسته است و امکان ثبت یا تغییر عملیات وجود ندارد.',
            ]);
        }

        $year = $period?->fiscalYear ?: $this->fiscalYearForDate($date);
        if ($year && $year->status === 'closed') {
            throw ValidationException::withMessages([
                'date' => 'بازه مالی بسته است و امکان ثبت یا تغییر عملیات وجود ندارد.',
            ]);
        }
    }

    private function normalizeDate(CarbonInterface|string $date): string
    {
        if ($date instanceof CarbonInterface) {
            return $date->toDateString();
        }

        return jalaliToGregorianDate($date) ?: $date;
    }

    public function close(FiscalPeriod $period, ?int $userId = null): FiscalPeriod
    {
        return DB::transaction(function () use ($period, $userId) {
            $period->loadMissing('fiscalYear.periods');
            $year = $period->fiscalYear ?? throw new RuntimeException('سال مالی دوره پیدا نشد.');

            $this->removeGeneratedClosingArtifacts($year);
            $this->assertReadyToClose($year);

            $nextYear = $this->nextFiscalYear($year);
            $this->createProfitAndLossClosingDocument($year, $userId);
            $this->createOpeningBalanceDocument($year, $nextYear, $userId);
            $this->createOpeningInventoryDocuments($year, $nextYear, $userId);

            $year->periods()->update([
                'status' => 'closed',
                'is_active' => false,
                'closed_at' => now(),
                'closed_by' => $userId,
            ]);

            $year->update([
                'status' => 'closed',
                'closed_at' => now(),
            ]);

            return $period->refresh();
        });
    }

    public function reopen(FiscalPeriod $period): FiscalPeriod
    {
        return DB::transaction(function () use ($period) {
            $period->loadMissing('fiscalYear.periods');
            $year = $period->fiscalYear ?? throw new RuntimeException('سال مالی دوره پیدا نشد.');

            $this->removeGeneratedClosingArtifacts($year);

            $year->periods()->update([
                'status' => 'open',
                'is_active' => true,
                'closed_at' => null,
                'closed_by' => null,
            ]);

            $year->update([
                'status' => 'open',
                'closed_at' => null,
            ]);

            if ($firstPeriod = $year->periods()->orderBy('period_number')->first()) {
                $this->activatePeriod($firstPeriod);
            }

            return $period->refresh();
        });
    }

    private function assertReadyToClose(FiscalYear $year): void
    {
        $draftDocuments = AccountingDocument::query()
            ->whereBetween('document_date', [$year->start_date, $year->end_date])
            ->where('status', 'draft')
            ->count();

        if ($draftDocuments > 0) {
            throw new RuntimeException("برای بستن سال مالی، ابتدا {$draftDocuments} سند حسابداری پیش‌نویس را قطعی کنید.");
        }

        $unbalanced = AccountingDocument::query()
            ->whereBetween('document_date', [$year->start_date, $year->end_date])
            ->where('status', 'posted')
            ->whereHas('lines')
            ->get()
            ->first(fn (AccountingDocument $document) => abs($document->debit_total - $document->credit_total) >= 0.01);

        if ($unbalanced) {
            throw new RuntimeException('سند حسابداری نامتوازن وجود دارد: ' . $unbalanced->number);
        }

        $draftInventory = InventoryDocument::query()
            ->whereBetween('document_date', [$year->start_date, $year->end_date])
            ->where('status', 'draft')
            ->count();

        if ($draftInventory > 0) {
            throw new RuntimeException("برای بستن سال مالی، ابتدا {$draftInventory} سند انبار پیش‌نویس را تعیین تکلیف کنید.");
        }

        $negativeStock = $this->inventoryBalances($year)
            ->first(fn ($row) => (float) $row->quantity < -0.0001);

        if ($negativeStock) {
            throw new RuntimeException('موجودی منفی برای کالا/انبار وجود دارد و سال مالی قابل بستن نیست.');
        }
    }

    private function nextFiscalYear(FiscalYear $year): FiscalYear
    {
        $next = FiscalYear::firstOrCreate(
            ['jalali_year' => $year->jalali_year + 1],
            [
                'title' => 'دوره مالی ' . ($year->jalali_year + 1),
                'start_date' => $year->start_date->copy()->addYear()->toDateString(),
                'end_date' => $year->end_date->copy()->addYear()->toDateString(),
                'currency' => $year->currency,
                'status' => 'open',
            ]
        );

        if ($next->status !== 'open') {
            $next->update(['status' => 'open', 'closed_at' => null]);
        }

        $next->periods()->firstOrCreate(
            ['period_number' => 1],
            [
                'title' => $next->title,
                'start_date' => $next->start_date,
                'end_date' => $next->end_date,
                'status' => 'open',
            ]
        );

        return $next->refresh();
    }

    private function createProfitAndLossClosingDocument(FiscalYear $year, ?int $userId): ?AccountingDocument
    {
        $retained = $this->retainedEarningsAccount();
        $lines = [];

        foreach ($this->accountBalances($year, onlyProfitAndLoss: true) as $row) {
            $balance = (float) $row->balance;

            if (abs($balance) < 0.01) {
                continue;
            }

            $lines[] = [
                'chart_account_id' => (int) $row->chart_account_id,
                'description' => 'بستن حساب سود و زیانی سال ' . $year->jalali_year,
                'debit' => $balance < 0 ? abs($balance) : 0,
                'credit' => $balance > 0 ? $balance : 0,
            ];
        }

        $debit = collect($lines)->sum('debit');
        $credit = collect($lines)->sum('credit');
        $difference = $debit - $credit;

        if (abs($difference) >= 0.01) {
            $lines[] = [
                'chart_account_id' => $retained->id,
                'description' => 'انتقال سود و زیان سال ' . $year->jalali_year,
                'debit' => $difference < 0 ? abs($difference) : 0,
                'credit' => $difference > 0 ? $difference : 0,
            ];
        }

        return $this->createAccountingDocument(
            year: $year,
            period: $year->periods()->orderByDesc('end_date')->first(),
            date: $year->end_date->toDateString(),
            numberKey: 'year_closing_document',
            prefix: 'CLOSE-',
            description: 'سند اختتامیه سود و زیان سال ' . $year->jalali_year,
            lines: $lines,
            userId: $userId
        );
    }

    private function createOpeningBalanceDocument(FiscalYear $closedYear, FiscalYear $nextYear, ?int $userId): ?AccountingDocument
    {
        $lines = [];

        foreach ($this->accountBalances($closedYear, onlyBalanceSheet: true) as $row) {
            $balance = (float) $row->balance;

            if (abs($balance) < 0.01) {
                continue;
            }

            $lines[] = [
                'chart_account_id' => (int) $row->chart_account_id,
                'party_id' => $row->party_id ? (int) $row->party_id : null,
                'project_id' => $row->project_id ? (int) $row->project_id : null,
                'bank_account_id' => $row->bank_account_id ? (int) $row->bank_account_id : null,
                'cashbox_id' => $row->cashbox_id ? (int) $row->cashbox_id : null,
                'description' => 'مانده افتتاحیه منتقل‌شده از سال ' . $closedYear->jalali_year,
                'debit' => $balance > 0 ? $balance : 0,
                'credit' => $balance < 0 ? abs($balance) : 0,
            ];
        }

        return $this->createAccountingDocument(
            year: $nextYear,
            period: $nextYear->periods()->orderBy('start_date')->first(),
            date: $nextYear->start_date->toDateString(),
            numberKey: 'year_opening_document',
            prefix: 'OPEN-',
            description: 'سند افتتاحیه سال ' . $nextYear->jalali_year . ' از مانده‌های سال ' . $closedYear->jalali_year,
            lines: $lines,
            userId: $userId,
            sourceYear: $closedYear
        );
    }

    private function createOpeningInventoryDocuments(FiscalYear $closedYear, FiscalYear $nextYear, ?int $userId): void
    {
        $rows = $this->inventoryBalances($closedYear)
            ->filter(fn ($row) => (float) $row->quantity > 0.0001)
            ->groupBy('warehouse_id');

        foreach ($rows as $warehouseId => $items) {
            $document = InventoryDocument::create([
                'fiscal_year_id' => $nextYear->id,
                'number' => $this->numbering->next('inventory_opening', 'IO-'),
                'type' => 'receipt',
                'document_date' => $nextYear->start_date->toDateString(),
                'document_time' => now()->format('H:i:s'),
                'warehouse_id' => $warehouseId,
                'source_type' => FiscalYear::class,
                'source_id' => $closedYear->id,
                'entry_mode' => 'automatic',
                'status' => 'confirmed',
                'description' => 'موجودی اول دوره سال ' . $nextYear->jalali_year,
                'created_by' => $userId,
                'confirmed_by' => $userId,
                'confirmed_at' => now(),
            ]);

            foreach ($items as $item) {
                $quantity = (float) $item->quantity;
                $totalValue = (float) $item->total_value;
                $unitPrice = $quantity > 0 ? max($totalValue / $quantity, 0) : 0;

                $document->lines()->create([
                    'item_id' => (int) $item->item_id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $quantity * $unitPrice,
                    'description' => 'انتقال موجودی پایان سال ' . $closedYear->jalali_year,
                ]);
            }
        }
    }

    private function accountBalances(FiscalYear $year, bool $onlyProfitAndLoss = false, bool $onlyBalanceSheet = false): Collection
    {
        return DB::table('accounting_document_lines as l')
            ->join('accounting_documents as d', 'd.id', '=', 'l.accounting_document_id')
            ->join('chart_accounts as a', 'a.id', '=', 'l.chart_account_id')
            ->whereNull('d.deleted_at')
            ->where('d.status', 'posted')
            ->whereBetween('d.document_date', [$year->start_date->toDateString(), $year->end_date->toDateString()])
            ->when($onlyProfitAndLoss, fn ($query) => $query->where(fn ($query) => $query->where('a.code', 'like', '4%')->orWhere('a.code', 'like', '5%')))
            ->when($onlyBalanceSheet, fn ($query) => $query->where(fn ($query) => $query->where('a.code', 'not like', '4%')->where('a.code', 'not like', '5%')))
            ->selectRaw('
                l.chart_account_id,
                l.party_id,
                l.project_id,
                l.bank_account_id,
                l.cashbox_id,
                SUM(l.debit - l.credit) as balance
            ')
            ->groupBy('l.chart_account_id', 'l.party_id', 'l.project_id', 'l.bank_account_id', 'l.cashbox_id')
            ->get();
    }

   private function inventoryBalances(FiscalYear $year): Collection
{
    $start = $year->start_date->toDateString();
    $end = $year->end_date->toDateString();

    return DB::table('inventory_document_lines as l')
        ->join('inventory_documents as d', 'd.id', '=', 'l.inventory_document_id')
        ->where('d.status', 'confirmed')
        ->whereBetween('d.document_date', [$start, $end])
        ->whereIn('d.type', ['receipt', 'issue', 'transfer', 'consumption'])
        ->selectRaw("
            l.item_id,
            CASE
                WHEN d.type = 'transfer'
                    THEN d.target_warehouse_id
                ELSE d.warehouse_id
            END as warehouse_id,
            SUM(
                CASE
                    WHEN d.type = 'receipt' THEN l.quantity
                    WHEN d.type = 'transfer' THEN l.quantity
                    ELSE -l.quantity
                END
            ) as quantity,
            SUM(
                CASE
                    WHEN d.type = 'receipt' THEN l.line_total
                    WHEN d.type = 'transfer' THEN l.line_total
                    ELSE -l.line_total
                END
            ) as total_value
        ")
        ->groupBy('l.item_id')
        ->groupByRaw("
            CASE
                WHEN d.type = 'transfer'
                    THEN d.target_warehouse_id
                ELSE d.warehouse_id
            END
        ")
        ->get();
}

    private function createAccountingDocument(
        FiscalYear $year,
        ?FiscalPeriod $period,
        string $date,
        string $numberKey,
        string $prefix,
        string $description,
        array $lines,
        ?int $userId,
        ?FiscalYear $sourceYear = null
    ): ?AccountingDocument {
        $lines = collect($lines)
            ->filter(fn ($line) => ((float) ($line['debit'] ?? 0) > 0.009) || ((float) ($line['credit'] ?? 0) > 0.009))
            ->values();

        if ($lines->isEmpty()) {
            return null;
        }

        $debit = $lines->sum(fn ($line) => (float) ($line['debit'] ?? 0));
        $credit = $lines->sum(fn ($line) => (float) ($line['credit'] ?? 0));

        if (abs($debit - $credit) >= 0.01) {
            throw new RuntimeException('سند انتقال مانده‌ها نامتوازن است.');
        }

        $document = AccountingDocument::create([
            'fiscal_year_id' => $year->id,
            'fiscal_period_id' => $period?->id,
            'number' => $this->numbering->next($numberKey, $prefix),
            'document_date' => $date,
            'type' => 'closing',
            'status' => 'posted',
            'currency' => $year->currency,
            'source_type' => FiscalYear::class,
            'source_id' => ($sourceYear ?? $year)->id,
            'description' => $description,
            'created_by' => $userId,
            'posted_at' => now(),
            'posted_by' => $userId,
        ]);

        foreach ($lines as $line) {
            $document->lines()->create([
                'chart_account_id' => $line['chart_account_id'],
                'detail_account_id' => $line['detail_account_id'] ?? null,
                'party_id' => $line['party_id'] ?? null,
                'project_id' => $line['project_id'] ?? null,
                'bank_account_id' => $line['bank_account_id'] ?? null,
                'cashbox_id' => $line['cashbox_id'] ?? null,
                'description' => $line['description'] ?? null,
                'debit' => (float) ($line['debit'] ?? 0),
                'credit' => (float) ($line['credit'] ?? 0),
                'currency' => $year->currency,
                'exchange_rate' => 1,
            ]);
        }

        return $document;
    }

    private function removeGeneratedClosingArtifacts(FiscalYear $year): void
    {
        $nextYear = FiscalYear::where('jalali_year', $year->jalali_year + 1)->first();

        $documents = AccountingDocument::withTrashed()
            ->where('source_type', FiscalYear::class)
            ->where('source_id', $year->id)
            ->where('type', 'closing')
            ->get();

        foreach ($documents as $document) {
            $document->lines()->delete();
            $document->forceDelete();
        }

        if ($nextYear) {
            $inventoryDocuments = InventoryDocument::where('source_type', FiscalYear::class)
                ->where('source_id', $year->id)
                ->where('fiscal_year_id', $nextYear->id)
                ->get();

            foreach ($inventoryDocuments as $document) {
                $document->lines()->delete();
                $document->delete();
            }
        }
    }

    private function retainedEarningsAccount(): ChartAccount
    {
        $equity = ChartAccount::firstOrCreate(
            ['code' => '3'],
            ['title' => 'حقوق مالکانه', 'level' => 'group', 'nature' => 'credit', 'is_active' => true, 'is_system' => true]
        );

        $parent = ChartAccount::firstOrCreate(
            ['code' => '31'],
            ['parent_id' => $equity->id, 'title' => 'سرمایه و سود انباشته', 'level' => 'ledger', 'nature' => 'credit', 'is_active' => true, 'is_system' => true]
        );

        return ChartAccount::firstOrCreate(
            ['code' => '3102'],
            ['parent_id' => $parent->id, 'title' => 'سود و زیان انباشته', 'level' => 'subsidiary', 'nature' => 'credit', 'is_active' => true, 'is_system' => true]
        );
    }
}
