<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ChartAccount;
use App\Models\Party;

foreach (['3', '32', '3201', '3202'] as $code) {
    $account = ChartAccount::where('code', $code)->first();
    echo $code . ': ' . ($account?->title ?? 'missing') . PHP_EOL;
}

Party::query()
    ->whereIn('id', [514, 515])
    ->with('types')
    ->get()
    ->each(function ($p) {
        echo "party {$p->id} | {$p->code} | {$p->name} | {$p->detail_code} | {$p->types->pluck('title')->join(', ')}\n";
    });
