<?php

namespace App\Services;

use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\FiscalYearNumberingCounter;
use App\Models\NumberingSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class NumberingService
{
    public const FISCAL_YEAR_KEYS = [
        'sale_proforma',
        'sale_invoice',
        'purchase_invoice',
        'inventory_receipt',
        'inventory_issue',
        'inventory_transfer',
        'inventory_closing',
        'inventory_opening',
        'inventory_consumption',
        'accounting_document',
        'treasury_transaction',
        'receipt_voucher',
        'payment_voucher',
        'insurance_payment',
    ];

    /**
     * @return list<array{key: string, label: string, scope: string, default_prefix: string, default_padding: int, sort_order: int}>
     */
    public function definitions(): array
    {
        return [
            ['key' => 'party', 'label' => 'کد طرف حساب', 'scope' => 'global', 'default_prefix' => 'P-', 'default_padding' => 5, 'sort_order' => 10],
            ['key' => 'party_detail', 'label' => 'کد تفصیل طرف حساب', 'scope' => 'global', 'default_prefix' => 'D-', 'default_padding' => 5, 'sort_order' => 20],
            ['key' => 'item', 'label' => 'کد کالا/خدمت', 'scope' => 'global', 'default_prefix' => 'I-', 'default_padding' => 5, 'sort_order' => 30],
            ['key' => 'project', 'label' => 'شماره پروژه', 'scope' => 'global', 'default_prefix' => 'PRJ-', 'default_padding' => 5, 'sort_order' => 40],
            ['key' => 'production_order', 'label' => 'شماره سفارش تولید', 'scope' => 'global', 'default_prefix' => 'PO-', 'default_padding' => 5, 'sort_order' => 50],
            ['key' => 'crm_lead', 'label' => 'سرنخ CRM', 'scope' => 'global', 'default_prefix' => 'LD-', 'default_padding' => 5, 'sort_order' => 55],
            ['key' => 'crm_opportunity', 'label' => 'فرصت CRM', 'scope' => 'global', 'default_prefix' => 'OPP-', 'default_padding' => 5, 'sort_order' => 56],
            ['key' => 'sale_proforma', 'label' => 'پیش‌فاکتور فروش', 'scope' => 'fiscal_year', 'default_prefix' => 'SP-', 'default_padding' => 5, 'sort_order' => 110],
            ['key' => 'sale_invoice', 'label' => 'فاکتور فروش', 'scope' => 'fiscal_year', 'default_prefix' => 'SI-', 'default_padding' => 5, 'sort_order' => 120],
            ['key' => 'purchase_invoice', 'label' => 'فاکتور خرید', 'scope' => 'fiscal_year', 'default_prefix' => 'PI-', 'default_padding' => 5, 'sort_order' => 130],
            ['key' => 'inventory_receipt', 'label' => 'رسید انبار', 'scope' => 'fiscal_year', 'default_prefix' => 'IR-', 'default_padding' => 5, 'sort_order' => 210],
            ['key' => 'inventory_issue', 'label' => 'حواله انبار', 'scope' => 'fiscal_year', 'default_prefix' => 'II-', 'default_padding' => 5, 'sort_order' => 220],
            ['key' => 'inventory_transfer', 'label' => 'انتقال انبار', 'scope' => 'fiscal_year', 'default_prefix' => 'IT-', 'default_padding' => 5, 'sort_order' => 230],
            ['key' => 'inventory_closing', 'label' => 'بستن موجودی انبار', 'scope' => 'fiscal_year', 'default_prefix' => 'IC-', 'default_padding' => 5, 'sort_order' => 240],
            ['key' => 'inventory_opening', 'label' => 'افتتاحیه انبار', 'scope' => 'fiscal_year', 'default_prefix' => 'IO-', 'default_padding' => 5, 'sort_order' => 250],
            ['key' => 'inventory_consumption', 'label' => 'مصرف مواد', 'scope' => 'fiscal_year', 'default_prefix' => 'IC-', 'default_padding' => 5, 'sort_order' => 260],
            ['key' => 'accounting_document', 'label' => 'سند حسابداری', 'scope' => 'fiscal_year', 'default_prefix' => 'ACC-', 'default_padding' => 5, 'sort_order' => 310],
            ['key' => 'treasury_transaction', 'label' => 'تراکنش خزانه', 'scope' => 'fiscal_year', 'default_prefix' => 'TR-', 'default_padding' => 5, 'sort_order' => 320],
            ['key' => 'receipt_voucher', 'label' => 'رسید دریافت', 'scope' => 'fiscal_year', 'default_prefix' => 'RV-', 'default_padding' => 5, 'sort_order' => 330],
            ['key' => 'payment_voucher', 'label' => 'رسید پرداخت', 'scope' => 'fiscal_year', 'default_prefix' => 'PV-', 'default_padding' => 5, 'sort_order' => 340],
            ['key' => 'insurance_payment', 'label' => 'پرداخت بیمه', 'scope' => 'fiscal_year', 'default_prefix' => 'IP-', 'default_padding' => 5, 'sort_order' => 350],
        ];
    }

    /**
     * @return array{key: string, label: string, scope: string, default_prefix: string, default_padding: int, sort_order: int}|null
     */
    public function definition(string $key): ?array
    {
        foreach ($this->definitions() as $definition) {
            if ($definition['key'] === $key) {
                return $definition;
            }
        }

        return null;
    }

    public function ensureDefaults(): void
    {
        foreach ($this->definitions() as $definition) {
            NumberingSetting::query()->firstOrCreate(
                ['document_key' => $definition['key']],
                $this->defaultSettingAttributes($definition)
            );
        }
    }

    /**
     * @param  array{key: string, label: string, scope: string, default_prefix: string, default_padding: int, sort_order: int}  $definition
     * @return array<string, mixed>
     */
    private function defaultSettingAttributes(array $definition): array
    {
        $attributes = [
            'prefix' => $definition['default_prefix'],
            'next_number' => 1,
            'padding' => $definition['default_padding'],
        ];

        if (Schema::hasColumn('numbering_settings', 'label')) {
            $attributes['label'] = $definition['label'];
        }

        if (Schema::hasColumn('numbering_settings', 'reuse_deleted_numbers')) {
            $attributes['reuse_deleted_numbers'] = false;
        }

        if (Schema::hasColumn('numbering_settings', 'sort_order')) {
            $attributes['sort_order'] = $definition['sort_order'];
        }

        return $attributes;
    }

    public function next(string $key, ?string $fallbackPrefix = null, ?int $fiscalYearId = null): string
    {
        return DB::transaction(function () use ($key, $fallbackPrefix, $fiscalYearId) {
            $this->ensureDefaults();

            $template = $this->templateFor($key, $fallbackPrefix);
            $prefix = $template['prefix'];
            $padding = $template['padding'];
            $reuseDeleted = $template['reuse_deleted_numbers'];

            if ($this->isFiscalYearScoped($key)) {
                $fiscalYearId = $this->resolveFiscalYearId($fiscalYearId);
                $counter = $this->counterFor($fiscalYearId, $key);
                $sequence = $this->resolveNextSequence($key, $prefix, $fiscalYearId, $reuseDeleted, (int) $counter->next_number);
                $number = $prefix . str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT);

                $counter->update(['next_number' => $sequence + 1]);

                return $number;
            }

            $setting = NumberingSetting::query()
                ->where('document_key', $key)
                ->lockForUpdate()
                ->first();

            if (! $setting) {
                $definition = $this->definition($key);

                $setting = NumberingSetting::create(array_merge(
                    ['document_key' => $key],
                    $definition
                        ? $this->defaultSettingAttributes($definition)
                        : [
                            'prefix' => $prefix,
                            'next_number' => 1,
                            'padding' => $padding,
                        ]
                ));
            }

            $sequence = $this->resolveNextSequence($key, $prefix, null, $reuseDeleted, (int) $setting->next_number);
            $number = $prefix . str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT);

            $setting->update([
                'prefix' => $prefix,
                'padding' => $padding,
                'next_number' => $sequence + 1,
            ]);

            return $number;
        });
    }

    public function storedNextNumber(string $key, ?int $fiscalYearId = null): int
    {
        if ($this->isFiscalYearScoped($key)) {
            $fiscalYearId = $this->resolveFiscalYearId($fiscalYearId);
            $counter = FiscalYearNumberingCounter::query()
                ->where('fiscal_year_id', $fiscalYearId)
                ->where('document_key', $key)
                ->first();

            return (int) ($counter?->next_number ?: 1);
        }

        $setting = NumberingSetting::query()->where('document_key', $key)->first();

        return (int) ($setting?->next_number ?: 1);
    }

    public function lastUsedSequence(string $key, string $prefix, ?int $fiscalYearId = null): int
    {
        if ($this->isFiscalYearScoped($key)) {
            $fiscalYearId = $this->resolveFiscalYearId($fiscalYearId);

            return $this->currentFiscalYearSequence($key, $prefix, $fiscalYearId);
        }

        return $this->currentGlobalSequence($key, $prefix);
    }

    public function syncCountersFromDocuments(?int $fiscalYearId = null): void
    {
        $this->ensureDefaults();

        DB::transaction(function () use ($fiscalYearId) {
            foreach ($this->definitions() as $definition) {
                $key = $definition['key'];
                $setting = NumberingSetting::query()->where('document_key', $key)->first();
                $prefix = $setting?->prefix ?: $definition['default_prefix'];
                $lastUsed = $this->lastUsedSequence($key, $prefix, $fiscalYearId);
                $nextNumber = $lastUsed + 1;

                if ($definition['scope'] === 'fiscal_year') {
                    $yearId = $this->resolveFiscalYearId($fiscalYearId);

                    FiscalYearNumberingCounter::query()->updateOrCreate(
                        [
                            'fiscal_year_id' => $yearId,
                            'document_key' => $key,
                        ],
                        [
                            'next_number' => $nextNumber,
                        ]
                    );
                } else {
                    NumberingSetting::query()
                        ->where('document_key', $key)
                        ->update(['next_number' => $nextNumber]);
                }
            }
        });
    }

    public function resolveActiveFiscalYearId(): int
    {
        return $this->resolveFiscalYearId(null);
    }

    public function resetFiscalYearCounters(FiscalYear $year): void
    {
        foreach (self::FISCAL_YEAR_KEYS as $key) {
            FiscalYearNumberingCounter::query()->updateOrCreate(
                [
                    'fiscal_year_id' => $year->id,
                    'document_key' => $key,
                ],
                [
                    'next_number' => 1,
                ]
            );
        }
    }

    public function isFiscalYearScoped(string $key): bool
    {
        return in_array($key, self::FISCAL_YEAR_KEYS, true);
    }

    private function resolveNextSequence(
        string $key,
        string $prefix,
        ?int $fiscalYearId,
        bool $reuseDeleted,
        int $storedNext
    ): int {
        $lastUsed = $fiscalYearId
            ? $this->currentFiscalYearSequence($key, $prefix, $fiscalYearId)
            : $this->currentGlobalSequence($key, $prefix);

        if ($reuseDeleted) {
            $used = $fiscalYearId
                ? $this->usedFiscalYearSequences($key, $prefix, $fiscalYearId)
                : $this->usedGlobalSequences($key, $prefix);

            for ($candidate = 1; $candidate <= max($lastUsed, $storedNext); $candidate++) {
                if (! in_array($candidate, $used, true)) {
                    return $candidate;
                }
            }

            return max($lastUsed, $storedNext - 1, 0) + 1;
        }

        return max($storedNext, $lastUsed + 1);
    }

    /**
     * @return array{prefix: string, padding: int, reuse_deleted_numbers: bool}
     */
    private function templateFor(string $key, ?string $fallbackPrefix): array
    {
        $setting = NumberingSetting::query()->where('document_key', $key)->first();
        $definition = $this->definition($key);

        return [
            'prefix' => $setting?->prefix ?: ($fallbackPrefix ?? $definition['default_prefix'] ?? strtoupper(substr($key, 0, 3)) . '-'),
            'padding' => (int) ($setting?->padding ?: ($definition['default_padding'] ?? 5)),
            'reuse_deleted_numbers' => Schema::hasColumn('numbering_settings', 'reuse_deleted_numbers')
                ? (bool) ($setting?->reuse_deleted_numbers ?? false)
                : false,
        ];
    }

    private function counterFor(int $fiscalYearId, string $key): FiscalYearNumberingCounter
    {
        return FiscalYearNumberingCounter::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('document_key', $key)
            ->lockForUpdate()
            ->first()
            ?? FiscalYearNumberingCounter::create([
                'fiscal_year_id' => $fiscalYearId,
                'document_key' => $key,
                'next_number' => 1,
            ]);
    }

    private function resolveFiscalYearId(?int $fiscalYearId): int
    {
        if ($fiscalYearId) {
            return $fiscalYearId;
        }

        $period = FiscalPeriod::query()
            ->with('fiscalYear')
            ->where('is_active', true)
            ->latest('id')
            ->first()
            ?? FiscalPeriod::query()
                ->where('status', 'open')
                ->latest('id')
                ->first();

        $resolved = $period?->fiscal_year_id ?? $period?->fiscalYear?->id;

        if (! $resolved) {
            $year = FiscalYear::query()->where('status', 'open')->orderByDesc('start_date')->first()
                ?? FiscalYear::query()->orderByDesc('start_date')->first();

            $resolved = $year?->id;
        }

        if (! $resolved) {
            throw new RuntimeException('سال مالی فعال برای شماره‌گذاری اسناد یافت نشد.');
        }

        return (int) $resolved;
    }

    private function currentFiscalYearSequence(string $key, string $prefix, int $fiscalYearId): int
    {
        return max($this->usedFiscalYearSequences($key, $prefix, $fiscalYearId) ?: [0]);
    }

    private function currentGlobalSequence(string $key, string $prefix): int
    {
        return max($this->usedGlobalSequences($key, $prefix) ?: [0]);
    }

    /**
     * @return list<int>
     */
    private function usedFiscalYearSequences(string $key, string $prefix, int $fiscalYearId): array
    {
        return match ($key) {
            'accounting_document' => $this->sequencesFromTable('accounting_documents', $prefix, $fiscalYearId, true),
            'treasury_transaction' => $this->sequencesFromTable('treasury_transactions', $prefix, $fiscalYearId, true),
            'sale_proforma' => $this->sequencesFromInvoices($prefix, $fiscalYearId, 'sale', 'proforma'),
            'sale_invoice' => $this->sequencesFromInvoices($prefix, $fiscalYearId, 'sale', 'invoice'),
            'purchase_invoice' => $this->sequencesFromInvoices($prefix, $fiscalYearId, 'purchase', 'invoice'),
            'inventory_receipt', 'inventory_issue', 'inventory_transfer', 'inventory_closing', 'inventory_opening', 'inventory_consumption'
                => $this->sequencesFromInventory($prefix, $fiscalYearId, $key),
            default => [],
        };
    }

    /**
     * @return list<int>
     */
    private function usedGlobalSequences(string $key, string $prefix): array
    {
        return match ($key) {
            'party' => $this->sequencesFromTable('parties', $prefix, null, false, 'code'),
            'party_detail' => $this->sequencesFromTable('parties', $prefix, null, false, 'detail_code'),
            'item' => $this->sequencesFromTable('items', $prefix, null, false, 'code'),
            'project' => $this->sequencesFromTable('projects', $prefix, null, false, 'project_number'),
            'production_order' => $this->sequencesFromTable('production_orders', $prefix, null, false),
            default => [],
        };
    }

    /**
     * @return list<int>
     */
    private function sequencesFromInvoices(string $prefix, int $fiscalYearId, string $direction, string $documentType): array
    {
        $fiscalYear = FiscalYear::query()->find($fiscalYearId);

        $query = DB::table('invoices')
            ->where('direction', $direction)
            ->where('document_type', $documentType);

        $this->applyFiscalYearScope($query, 'invoices', 'invoice_date', $fiscalYearId, $fiscalYear);

        return $query
            ->pluck('number')
            ->map(fn (string $number) => $this->extractSequence($number, $prefix))
            ->filter(fn (int $sequence) => $sequence > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    private function sequencesFromInventory(string $prefix, int $fiscalYearId, string $key): array
    {
        $fiscalYear = FiscalYear::query()->find($fiscalYearId);

        $query = DB::table('inventory_documents');
        $this->applyFiscalYearScope($query, 'inventory_documents', 'document_date', $fiscalYearId, $fiscalYear);

        $type = match ($key) {
            'inventory_receipt' => 'receipt',
            'inventory_issue', 'inventory_closing', 'inventory_consumption' => 'issue',
            'inventory_transfer' => 'transfer',
            'inventory_opening' => 'receipt',
            default => null,
        };

        if ($type && ! in_array($key, ['inventory_closing', 'inventory_opening', 'inventory_consumption'], true)) {
            $query->where('type', $type);
        }

        return $query
            ->pluck('number')
            ->map(fn (string $number) => $this->extractSequence($number, $prefix))
            ->filter(fn (int $sequence) => $sequence > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    private function sequencesFromTable(
        string $table,
        string $prefix,
        ?int $fiscalYearId,
        bool $withSoftDeletes,
        string $column = 'number'
    ): array {
        if (! DB::getSchemaBuilder()->hasTable($table) || ! $this->hasColumn($table, $column)) {
            return [];
        }

        $fiscalYear = $fiscalYearId ? FiscalYear::query()->find($fiscalYearId) : null;
        $dateColumn = match ($table) {
            'accounting_documents' => 'document_date',
            'treasury_transactions' => 'transaction_date',
            default => null,
        };

        $query = DB::table($table);

        if ($fiscalYearId && $dateColumn && $this->hasColumn($table, 'fiscal_year_id')) {
            $this->applyFiscalYearScope($query, $table, $dateColumn, $fiscalYearId, $fiscalYear);
        } elseif ($fiscalYearId && $this->hasColumn($table, 'fiscal_year_id')) {
            $query->where('fiscal_year_id', $fiscalYearId);
        }

        if ($withSoftDeletes && $this->hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query
            ->pluck($column)
            ->filter(fn ($value) => filled($value))
            ->map(fn (string $number) => $this->extractSequence($number, $prefix))
            ->filter(fn (int $sequence) => $sequence > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function applyFiscalYearScope(
        \Illuminate\Database\Query\Builder $query,
        string $table,
        string $dateColumn,
        int $fiscalYearId,
        ?FiscalYear $fiscalYear
    ): void {
        if (! $this->hasColumn($table, 'fiscal_year_id')) {
            return;
        }

        $query->where(function ($scoped) use ($fiscalYearId, $fiscalYear, $table, $dateColumn) {
            $scoped->where($table . '.fiscal_year_id', $fiscalYearId);

            if ($fiscalYear) {
                $scoped->orWhere(function ($fallback) use ($fiscalYear, $table, $dateColumn) {
                    $fallback
                        ->whereNull($table . '.fiscal_year_id')
                        ->whereBetween($table . '.' . $dateColumn, [$fiscalYear->start_date, $fiscalYear->end_date]);
                });
            }
        });
    }

    private function extractSequence(string $number, string $prefix): int
    {
        $number = trim($number);

        if ($number === '') {
            return 0;
        }

        if ($prefix !== '' && str_starts_with($number, $prefix)) {
            return (int) preg_replace('/\D+/', '', substr($number, strlen($prefix))) ?: 0;
        }

        $compactPrefix = rtrim($prefix, '-');

        if ($compactPrefix !== '' && str_starts_with($number, $compactPrefix)) {
            return (int) preg_replace('/\D+/', '', substr($number, strlen($compactPrefix))) ?: 0;
        }

        if (preg_match('/(\d+)$/', $number, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    private function hasColumn(string $table, string $column): bool
    {
        return DB::getSchemaBuilder()->hasColumn($table, $column);
    }
}
