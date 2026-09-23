<?php

namespace App\Services;

use App\Models\FiscalYear;
use App\Models\FiscalYearNumberingCounter;
use App\Models\NumberingSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NumberingSettingService
{
    public function __construct(private NumberingService $numbering)
    {
    }

    /**
     * @return list<array{
     *     document_key: string,
     *     label: string,
     *     scope: string,
     *     prefix: string,
     *     padding: int,
     *     next_number: int,
     *     last_used: int,
     *     reuse_deleted_numbers: bool,
     *     preview: string
     * }>
     */
    public function rowsForFiscalYear(?int $fiscalYearId = null): array
    {
        $this->numbering->ensureDefaults();

        $fiscalYearId = $fiscalYearId ?: $this->numbering->resolveActiveFiscalYearId();

        return collect($this->numbering->definitions())
            ->map(function (array $definition) use ($fiscalYearId) {
                $setting = NumberingSetting::query()
                    ->where('document_key', $definition['key'])
                    ->first();

                $prefix = $setting?->prefix ?: $definition['default_prefix'];
                $padding = (int) ($setting?->padding ?: $definition['default_padding']);
                $reuse = Schema::hasColumn('numbering_settings', 'reuse_deleted_numbers')
                    ? (bool) ($setting?->reuse_deleted_numbers ?? false)
                    : false;
                $nextNumber = $this->numbering->storedNextNumber($definition['key'], $fiscalYearId);
                $lastUsed = $this->numbering->lastUsedSequence($definition['key'], $prefix, $fiscalYearId);

                return [
                    'document_key' => $definition['key'],
                    'label' => $setting?->label ?: $definition['label'],
                    'scope' => $definition['scope'],
                    'prefix' => $prefix,
                    'padding' => $padding,
                    'next_number' => $nextNumber,
                    'last_used' => $lastUsed,
                    'reuse_deleted_numbers' => $reuse,
                    'preview' => $prefix . str_pad((string) $nextNumber, $padding, '0', STR_PAD_LEFT),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array{document_key: string, prefix?: string, padding?: int, next_number?: int, reuse_deleted_numbers?: bool}>  $rows
     */
    public function updateRows(array $rows, ?int $fiscalYearId = null): void
    {
        $fiscalYearId = $fiscalYearId ?: $this->numbering->resolveActiveFiscalYearId();
        $this->numbering->ensureDefaults();

        DB::transaction(function () use ($rows, $fiscalYearId) {
            foreach ($rows as $row) {
                $key = (string) ($row['document_key'] ?? '');

                if ($key === '') {
                    continue;
                }

                $definition = $this->numbering->definition($key);

                if (! $definition) {
                    continue;
                }

                $setting = NumberingSetting::query()
                    ->where('document_key', $key)
                    ->lockForUpdate()
                    ->firstOrFail();

                $updates = [
                    'prefix' => (string) ($row['prefix'] ?? $setting->prefix ?? $definition['default_prefix']),
                    'padding' => max(1, min(12, (int) ($row['padding'] ?? $setting->padding ?? $definition['default_padding']))),
                ];

                if (Schema::hasColumn('numbering_settings', 'reuse_deleted_numbers')) {
                    $updates['reuse_deleted_numbers'] = (bool) ($row['reuse_deleted_numbers'] ?? $setting->reuse_deleted_numbers);
                }

                $setting->update($updates);

                $nextNumber = max(1, (int) ($row['next_number'] ?? $this->numbering->storedNextNumber($key, $fiscalYearId)));

                if ($definition['scope'] === 'fiscal_year') {
                    FiscalYearNumberingCounter::query()->updateOrCreate(
                        [
                            'fiscal_year_id' => $fiscalYearId,
                            'document_key' => $key,
                        ],
                        [
                            'next_number' => $nextNumber,
                        ]
                    );
                } else {
                    $setting->update(['next_number' => $nextNumber]);
                }
            }
        });
    }

    public function syncFromDocuments(?int $fiscalYearId = null): void
    {
        $this->numbering->syncCountersFromDocuments($fiscalYearId);
    }

    /**
     * @return list<FiscalYear>
     */
    public function fiscalYears(): array
    {
        return FiscalYear::query()
            ->orderByDesc('start_date')
            ->get()
            ->all();
    }
}
