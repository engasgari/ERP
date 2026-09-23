<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fiscal_year_numbering_counters')) {
            Schema::create('fiscal_year_numbering_counters', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
                $table->string('document_key');
                $table->unsignedInteger('next_number')->default(1);
                $table->timestamps();

                $table->unique(['fiscal_year_id', 'document_key'], 'fy_numbering_counters_year_key_unique');
            });
        }

        $this->backfillFiscalYearIds();
        $this->replaceDocumentNumberUniques();
        $this->backfillFiscalYearCounters();
    }

    public function down(): void
    {
        $this->restoreDocumentNumberUniques();

        if (Schema::hasColumn('treasury_transactions', 'fiscal_year_id')) {
            Schema::table('treasury_transactions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('fiscal_year_id');
            });
        }

        Schema::dropIfExists('fiscal_year_numbering_counters');
    }

    private function backfillFiscalYearIds(): void
    {
        foreach ([
            'accounting_documents' => 'document_date',
            'invoices' => 'invoice_date',
            'inventory_documents' => 'document_date',
        ] as $table => $dateColumn) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'fiscal_year_id')) {
                continue;
            }

            DB::statement("
                UPDATE {$table} AS target
                INNER JOIN fiscal_years AS fy
                    ON target.{$dateColumn} BETWEEN fy.start_date AND fy.end_date
                SET target.fiscal_year_id = fy.id
                WHERE target.fiscal_year_id IS NULL
            ");
        }

        if (Schema::hasTable('treasury_transactions') && ! Schema::hasColumn('treasury_transactions', 'fiscal_year_id')) {
            Schema::table('treasury_transactions', function (Blueprint $table) {
                $table->foreignId('fiscal_year_id')->nullable()->after('number')->constrained()->nullOnDelete();
            });

            DB::statement('
                UPDATE treasury_transactions AS tt
                INNER JOIN fiscal_years AS fy
                    ON tt.transaction_date BETWEEN fy.start_date AND fy.end_date
                SET tt.fiscal_year_id = fy.id
                WHERE tt.fiscal_year_id IS NULL
            ');
        }
    }

    private function replaceDocumentNumberUniques(): void
    {
        foreach ([
            'accounting_documents',
            'invoices',
            'inventory_documents',
        ] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $this->dropUniqueIndexIfExists($tableName, "{$tableName}_number_unique");

            $fiscalUnique = $tableName . '_fiscal_year_number_unique';
            if (! $this->indexExists($tableName, $fiscalUnique)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName, $fiscalUnique) {
                    $table->unique(['fiscal_year_id', 'number'], $fiscalUnique);
                });
            }
        }

        if (Schema::hasTable('treasury_transactions') && Schema::hasColumn('treasury_transactions', 'fiscal_year_id')) {
            $this->dropUniqueIndexIfExists('treasury_transactions', 'treasury_transactions_number_unique');

            if (! $this->indexExists('treasury_transactions', 'treasury_transactions_fiscal_year_number_unique')) {
                Schema::table('treasury_transactions', function (Blueprint $table) {
                    $table->unique(['fiscal_year_id', 'number'], 'treasury_transactions_fiscal_year_number_unique');
                });
            }
        }
    }

    private function restoreDocumentNumberUniques(): void
    {
        foreach ([
            'accounting_documents',
            'invoices',
            'inventory_documents',
            'treasury_transactions',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $this->dropUniqueIndexIfExists($table, "{$table}_fiscal_year_number_unique");

            if (! $this->indexExists($table, "{$table}_number_unique")) {
                Schema::table($table, function (Blueprint $table) {
                    $table->unique('number');
                });
            }
        }
    }

    private function backfillFiscalYearCounters(): void
    {
        if (! Schema::hasTable('fiscal_years')) {
            return;
        }

        $keys = [
            'accounting_document' => ['table' => 'accounting_documents', 'prefix' => 'ACC-'],
            'sale_proforma' => ['table' => 'invoices', 'prefix' => 'SP-', 'invoice' => 'sale_proforma'],
            'sale_invoice' => ['table' => 'invoices', 'prefix' => 'SI-', 'invoice' => 'sale_invoice'],
            'purchase_invoice' => ['table' => 'invoices', 'prefix' => 'PI-', 'invoice' => 'purchase_invoice'],
            'inventory_receipt' => ['table' => 'inventory_documents', 'prefix' => 'IR-', 'inventory_type' => 'receipt'],
            'inventory_issue' => ['table' => 'inventory_documents', 'prefix' => 'II-', 'inventory_type' => 'issue'],
            'inventory_transfer' => ['table' => 'inventory_documents', 'prefix' => 'IT-', 'inventory_type' => 'transfer'],
            'inventory_closing' => ['table' => 'inventory_documents', 'prefix' => 'IC-'],
            'inventory_opening' => ['table' => 'inventory_documents', 'prefix' => 'IO-'],
            'treasury_transaction' => ['table' => 'treasury_transactions', 'prefix' => 'TR-'],
        ];

        $now = now();

        foreach (DB::table('fiscal_years')->pluck('id') as $fiscalYearId) {
            foreach ($keys as $documentKey => $config) {
                $max = $this->maxSequenceForYear($fiscalYearId, $documentKey, $config);

                DB::table('fiscal_year_numbering_counters')->updateOrInsert(
                    [
                        'fiscal_year_id' => $fiscalYearId,
                        'document_key' => $documentKey,
                    ],
                    [
                        'next_number' => $max + 1,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function maxSequenceForYear(int $fiscalYearId, string $documentKey, array $config): int
    {
        $prefix = $config['prefix'];
        $table = $config['table'];

        if (! Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table)
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('number', 'like', $prefix . '%');

        if ($table === 'invoices') {
            match ($config['invoice'] ?? null) {
                'sale_proforma' => $query->where('direction', 'sale')->where('document_type', 'proforma'),
                'sale_invoice' => $query->where('direction', 'sale')->where('document_type', 'invoice'),
                'purchase_invoice' => $query->where('direction', 'purchase')->where('document_type', 'invoice'),
                default => null,
            };
        }

        if ($table === 'inventory_documents' && isset($config['inventory_type'])) {
            $query->where('type', $config['inventory_type']);
        }

        if ($table === 'treasury_transactions' && Schema::hasColumn('treasury_transactions', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if ($table === 'accounting_documents' && Schema::hasColumn('accounting_documents', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return (int) ($query
            ->pluck('number')
            ->map(fn (string $number) => (int) preg_replace('/^' . preg_quote($prefix, '/') . '/', '', $number))
            ->max() ?: 0);
    }

    private function dropUniqueIndexIfExists(string $table, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            Schema::table($table, function (Blueprint $table) use ($indexName) {
                $table->dropUnique($indexName);
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $database = Schema::getConnection()->getDatabaseName();

        $result = DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$database, $table, $indexName]
        );

        return ! empty($result);
    }
};
