<?php

$root = dirname(__DIR__);

$replacements = [
    'number_format(' => 'formatMoney(',
    "verta()->format('Y/m/d')" => 'todayJalaliDate()',
    "verta()->format('Y/m/d H:i')" => "formatJalaliDateTime(now())",
];

$vertaDatePattern = '/verta\(([^)]+)\)->format\(\'Y\/m\/d H:i\'\)/';
$vertaDateOnlyPattern = '/verta\(([^)]+)\)->format\(\'Y\/m\/d\'\)/';

$paths = array_merge(
    glob($root . '/resources/views/**/*.blade.php'),
    glob($root . '/resources/views/**/**/*.blade.php'),
    glob($root . '/app/Http/Controllers/*.php'),
    glob($root . '/app/Livewire/**/*.php'),
    glob($root . '/app/Models/*.php'),
);

$paths = array_unique($paths);
$changedFiles = 0;

foreach ($paths as $file) {
    if (! is_file($file)) {
        continue;
    }

    $original = file_get_contents($file);
    $content = $original;

    foreach ($replacements as $search => $replace) {
        $content = str_replace($search, $replace, $content);
    }

    $content = preg_replace($vertaDatePattern, 'formatJalaliDateTime($1)', $content);
    $content = preg_replace($vertaDateOnlyPattern, 'gregorianToJalaliDate($1)', $content);

    if ($content !== $original) {
        file_put_contents($file, $content);
        $changedFiles++;
        echo basename(dirname($file)) . '/' . basename($file) . PHP_EOL;
    }
}

echo "Updated {$changedFiles} files." . PHP_EOL;
