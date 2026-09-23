<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AccountingDocument;
use App\Models\FiscalYear;
use App\Models\InventoryDocument;
use App\Models\Invoice;

echo 'DB: ' . config('database.connections.mysql.database') . "\n\n";

foreach (FiscalYear::with('periods')->orderBy('jalali_year')->get() as $year) {
    $start = $year->start_date->toDateString();
    $end = $year->end_date->toDateString();

    echo "=== Jalali {$year->jalali_year} | id={$year->id} | status={$year->status} | {$start} .. {$end} ===\n";
    echo 'Periods: ' . $year->periods->count() . ' | active: ' . $year->periods->where('is_active', true)->pluck('period_number')->implode(', ') . "\n";

    $byYearId = AccountingDocument::where('fiscal_year_id', $year->id)->count();
    $byDatePosted = AccountingDocument::whereBetween('document_date', [$start, $end])->where('status', 'posted')->count();
    $byDateDraft = AccountingDocument::whereBetween('document_date', [$start, $end])->where('status', 'draft')->count();
    $closing = AccountingDocument::whereBetween('document_date', [$start, $end])->whereIn('type', ['closing', 'opening'])->count();

    echo "Accounting: fiscal_year_id={$byYearId} | posted_by_date={$byDatePosted} | draft_by_date={$byDateDraft} | closing/opening_by_date={$closing}\n";
    echo 'Invoices by fiscal_year_id: ' . Invoice::where('fiscal_year_id', $year->id)->count() . "\n";
    echo 'Inventory by fiscal_year_id: ' . InventoryDocument::where('fiscal_year_id', $year->id)->count() . "\n\n";
}

// Documents in typical 1404 range even if year not defined
$ranges = [
    1404 => ['2025-03-21', '2026-03-20'],
    1405 => ['2026-03-21', '2027-03-20'],
];

echo "=== Documents by typical Jalali year date ranges ===\n";
foreach ($ranges as $jalali => [$start, $end]) {
    $posted = AccountingDocument::whereBetween('document_date', [$start, $end])->where('status', 'posted')->count();
    $draft = AccountingDocument::whereBetween('document_date', [$start, $end])->where('status', 'draft')->count();
    echo "Jalali {$jalali} ({$start}..{$end}): posted={$posted}, draft={$draft}\n";
}
