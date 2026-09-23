<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AccountingDocument;
use App\Models\FiscalYear;
use App\Models\InventoryDocument;
use App\Models\Invoice;

foreach (FiscalYear::orderBy('jalali_year')->get() as $year) {
    echo "\n=== Year {$year->jalali_year} (id={$year->id}, status={$year->status}) ===\n";
    $docs = AccountingDocument::withTrashed()
        ->where('fiscal_year_id', $year->id)
        ->get(['id', 'number', 'type', 'status', 'source_type', 'source_id']);
    echo "Accounting docs (by fiscal_year_id): {$docs->count()}\n";
    foreach ($docs as $d) {
        echo "  {$d->number} | type={$d->type} | status={$d->status} | source=" . class_basename($d->source_type ?? '') . "#{$d->source_id}\n";
    }
    $sourceDocs = AccountingDocument::withTrashed()
        ->where('source_type', FiscalYear::class)
        ->where('source_id', $year->id)
        ->get(['id', 'number', 'type', 'fiscal_year_id']);
    if ($sourceDocs->isNotEmpty()) {
        echo "Docs sourced FROM this year (closing artifacts for next year):\n";
        foreach ($sourceDocs as $d) {
            echo "  {$d->number} | type={$d->type} | in_year_id={$d->fiscal_year_id}\n";
        }
    }
    echo 'Invoices: ' . Invoice::where('fiscal_year_id', $year->id)->count() . "\n";
    echo 'Inventory: ' . InventoryDocument::where('fiscal_year_id', $year->id)->count() . "\n";
}
