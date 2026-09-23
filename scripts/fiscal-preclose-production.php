<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AccountingDocument;
use App\Models\FiscalYear;
use App\Models\InventoryDocument;
use App\Services\FiscalPeriodService;

$year = FiscalYear::with('periods')->where('status', 'open')->orderByDesc('jalali_year')->first();

if (! $year) {
    echo "No open fiscal year.\n";
    exit(1);
}

$period = $year->periods()->orderBy('period_number')->first();
$start = $year->start_date->toDateString();
$end = $year->end_date->toDateString();

echo "Open year: {$year->jalali_year} (id={$year->id})\n";
echo "Period id={$period?->id} status={$period?->status} active=" . ($period?->is_active ? 'yes' : 'no') . "\n";
echo "Range: {$start} .. {$end}\n\n";

$draftDocs = AccountingDocument::whereBetween('document_date', [$start, $end])->where('status', 'draft')->count();
echo "Draft accounting documents: {$draftDocs}\n";

$unbalanced = AccountingDocument::whereBetween('document_date', [$start, $end])
    ->where('status', 'posted')
    ->whereHas('lines')
    ->get()
    ->filter(fn ($d) => abs($d->debit_total - $d->credit_total) >= 0.01);

echo 'Unbalanced posted documents: ' . $unbalanced->count() . "\n";
foreach ($unbalanced->take(5) as $doc) {
    echo "  - {$doc->number} debit={$doc->debit_total} credit={$doc->credit_total}\n";
}

$draftInv = InventoryDocument::whereBetween('document_date', [$start, $end])->where('status', 'draft')->count();
echo "Draft inventory documents: {$draftInv}\n";

$service = app(FiscalPeriodService::class);
$reflection = new ReflectionClass($service);
$method = $reflection->getMethod('inventoryBalances');
$method->setAccessible(true);
$negativeStock = collect($method->invoke($service, $year))->filter(fn ($row) => (float) $row->quantity < -0.0001);
echo 'Negative inventory rows: ' . $negativeStock->count() . "\n";
foreach ($negativeStock->take(5) as $row) {
    echo "  - item={$row->item_id} warehouse={$row->warehouse_id} qty={$row->quantity}\n";
}

$nextExists = FiscalYear::where('jalali_year', $year->jalali_year + 1)->exists();
echo 'Next year exists: ' . ($nextExists ? 'yes' : 'no (auto-created on close)') . "\n";

$missingFiscalYearId = AccountingDocument::whereBetween('document_date', [$start, $end])
    ->where('status', 'posted')
    ->whereNull('fiscal_year_id')
    ->count();
echo "Posted docs in range without fiscal_year_id: {$missingFiscalYearId}\n";

$outsideYearId = AccountingDocument::where('fiscal_year_id', $year->id)
    ->where(function ($q) use ($start, $end) {
        $q->whereDate('document_date', '<', $start)->orWhereDate('document_date', '>', $end);
    })
    ->count();
echo "Docs with fiscal_year_id={$year->id} but date outside range: {$outsideYearId}\n";

echo "\nReady to close (service pre-checks): ";
try {
    $check = $reflection->getMethod('assertReadyToClose');
    $check->setAccessible(true);
    $check->invoke($service, $year);
    echo "YES\n";
} catch (Throwable $e) {
    echo "NO — {$e->getMessage()}\n";
}
