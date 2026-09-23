<?php

require __DIR__ . '/vendor/autoload.php';

$path = 'f:/PiofyDevice/PiofyAttendanceData(1405-06-25).xlsx';
echo 'exists=' . (file_exists($path) ? '1' : '0') . PHP_EOL;
echo 'size=' . (file_exists($path) ? filesize($path) : 0) . PHP_EOL;

// Prefer PhpSpreadsheet if available
if (class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
    foreach ($spreadsheet->getAllSheets() as $sheet) {
        echo 'SHEET=' . $sheet->getTitle() . PHP_EOL;
        $rows = $sheet->toArray(null, true, true, false);
        $max = min(15, count($rows));
        for ($i = 0; $i < $max; $i++) {
            echo 'R' . $i . '=' . json_encode($rows[$i], JSON_UNESCAPED_UNICODE) . PHP_EOL;
        }
        echo 'TOTAL_ROWS=' . count($rows) . PHP_EOL;
    }
    exit(0);
}

echo "NO_PHPSPREADSHEET\n";
