<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AccountingDocument;
use App\Models\FiscalYear;
use App\Models\InventoryDocument;

echo "=== Fiscal Years ===\n";
foreach (FiscalYear::with('periods')->orderBy('jalali_year')->get() as $year) {
    echo sprintf(
        "Year %d | id=%d | status=%s | %s .. %s\n",
        $year->jalali_year,
        $year->id,
        $year->status,
        $year->start_date?->toDateString(),
        $year->end_date?->toDateString()
    );
    foreach ($year->periods as $period) {
        echo sprintf(
            "  Period %d | status=%s | active=%s\n",
            $period->id,
            $period->status,
            $period->is_active ? 'yes' : 'no'
        );
    }
}

$openYear = FiscalYear::where('status', 'open')->orderByDesc('jalali_year')->first();
if (! $openYear) {
    echo "\nNo open fiscal year found.\n";
    exit(0);
}

echo "\n=== Pre-close checks for year {$openYear->jalali_year} ===\n";
$start = $openYear->start_date->toDateString();
$end = $openYear->end_date->toDateString();

$draftDocs = AccountingDocument::whereBetween('document_date', [$start, $end])->where('status', 'draft')->count();
echo "Draft accounting documents: {$draftDocs}\n";

$draftInv = InventoryDocument::whereBetween('document_date', [$start, $end])->where('status', 'draft')->count();
echo "Draft inventory documents: {$draftInv}\n";

$unbalanced = AccountingDocument::whereBetween('document_date', [$start, $end])
    ->where('status', 'posted')
    ->whereHas('lines')
    ->get()
    ->first(fn ($d) => abs($d->debit_total - $d->credit_total) >= 0.01);

echo 'Unbalanced posted document: ' . ($unbalanced ? $unbalanced->number : 'none') . "\n";

$nextYear = FiscalYear::where('jalali_year', $openYear->jalali_year + 1)->first();
echo 'Next year exists: ' . ($nextYear ? "yes (id={$nextYear->id}, status={$nextYear->status})" : 'no (will be auto-created)') . "\n";
