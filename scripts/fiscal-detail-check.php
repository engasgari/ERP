<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\FiscalYear;

$year = FiscalYear::where('jalali_year', 1404)->firstOrFail();
$start = $year->start_date->toDateString();
$end = $year->end_date->toDateString();

$dup = DB::table('accounting_document_lines as l')
    ->join('accounting_documents as d', 'd.id', '=', 'l.accounting_document_id')
    ->whereNull('d.deleted_at')
    ->where('d.status', 'posted')
    ->whereBetween('d.document_date', [$start, $end])
    ->whereNotNull('l.detail_account_id')
    ->selectRaw('l.chart_account_id, count(distinct l.detail_account_id) as detail_count, count(*) as line_count')
    ->groupBy('l.chart_account_id')
    ->having('detail_count', '>', 1)
    ->get();

echo 'Chart accounts with multiple detail accounts in 1404: ' . $dup->count() . "\n";
foreach ($dup as $row) {
    echo "  chart_account_id={$row->chart_account_id} details={$row->detail_count} lines={$row->line_count}\n";
}
