<?php

namespace App\Support;

use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class TaxElectronicBooksExcelExporter
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function download(string $filename, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $path = self::build($rows);

            try {
                readfile($path);
            } finally {
                @unlink($path);
            }
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function build(array $rows): string
    {
        $templatePath = resource_path('templates/tax-electronics-books-template.xlsx');

        if (! is_file($templatePath)) {
            throw new RuntimeException('قالب رسمی دفاتر الکترونیک مالیاتی یافت نشد.');
        }

        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('افزونه zip برای خروجی اکسل فعال نیست.');
        }

        $temp = tempnam(sys_get_temp_dir(), 'tax-books-xlsx-');

        if ($temp === false) {
            throw new RuntimeException('امکان ایجاد فایل موقت اکسل وجود ندارد.');
        }

        $xlsxPath = $temp . '.xlsx';

        if (! copy($templatePath, $xlsxPath)) {
            throw new RuntimeException('امکان کپی قالب اکسل وجود ندارد.');
        }

        @unlink($temp);

        $zip = new ZipArchive();

        if ($zip->open($xlsxPath) !== true) {
            throw new RuntimeException('امکان ایجاد فایل اکسل وجود ندارد.');
        }

        $blueprint = (string) $zip->getFromName('xl/worksheets/sheet1.xml');

        if ($blueprint === '') {
            $zip->close();
            throw new RuntimeException('ساختار قالب اکسل نامعتبر است.');
        }

        $zip->addFromString('xl/worksheets/sheet1.xml', self::buildSheetXml($blueprint, $rows));
        $zip->close();

        return $xlsxPath;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private static function buildSheetXml(string $blueprint, array $rows): string
    {
        if (! preg_match('/<sheetData>.*?<\/sheetData>/s', $blueprint, $matches)) {
            throw new RuntimeException('بخش sheetData در قالب اکسل یافت نشد.');
        }

        $headerRow = '';
        if (preg_match('/<row r="1"[^>]*>.*?<\/row>/s', $matches[0], $headerMatch)) {
            $headerRow = $headerMatch[0];
        }

        $dataRows = '';
        foreach ($rows as $index => $row) {
            $dataRows .= self::dataRowXml($index + 2, $row);
        }

        $sheetData = '<sheetData>' . $headerRow . $dataRows . '</sheetData>';
        $xml = preg_replace('/<sheetData>.*?<\/sheetData>/s', $sheetData, $blueprint, 1);

        if (! is_string($xml)) {
            throw new RuntimeException('امکان ساخت شیت اکسل وجود ندارد.');
        }

        $lastRow = max(1, count($rows) + 1);

        return preg_replace(
            '/<dimension ref="[^"]+"/',
            '<dimension ref="A1:I' . $lastRow . '"',
            $xml,
            1
        ) ?? $xml;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function dataRowXml(int $rowNumber, array $row): string
    {
        $cells = [
            'A' => self::textCell($row['row_number'] ?? ($rowNumber - 1), 1),
            'B' => self::textCell($row['date'] ?? '', 1),
            'C' => self::textCell($row['ledger_code'] ?? '', 1),
            'D' => self::textCell($row['ledger_title'] ?? '', 1),
            'E' => self::textCell($row['subsidiary_code'] ?? '', 1),
            'F' => self::textCell($row['subsidiary_title'] ?? '', 1),
            'G' => self::textCell($row['description'] ?? '', 1),
            'H' => self::amountCell($row['debit'] ?? 0),
            'I' => self::amountCell($row['credit'] ?? 0),
        ];

        $xml = '<row r="' . $rowNumber . '" spans="1:9" x14ac:dyDescent="0.6">';

        foreach ($cells as $column => $cell) {
            $xml .= '<c r="' . $column . $rowNumber . '"' . $cell['attrs'];

            if ($cell['inner'] === '') {
                $xml .= '/>';
            } else {
                $xml .= '>' . $cell['inner'] . '</c>';
            }
        }

        $xml .= '</row>';

        return $xml;
    }

    /**
     * @return array{attrs: string, inner: string}
     */
    private static function textCell(mixed $value, int $style): array
    {
        $text = trim((string) $value);

        if ($text === '') {
            return ['attrs' => ' s="' . $style . '"', 'inner' => ''];
        }

        return [
            'attrs' => ' s="' . $style . '" t="inlineStr"',
            'inner' => '<is><t>' . self::escapeXml($text) . '</t></is>',
        ];
    }

    /**
     * @return array{attrs: string, inner: string}
     */
    private static function amountCell(mixed $value): array
    {
        $amount = (float) $value;

        if ($amount <= 0) {
            return ['attrs' => ' s="1"', 'inner' => ''];
        }

        return [
            'attrs' => ' s="2"',
            'inner' => '<v>' . (int) round($amount) . '</v>',
        ];
    }

    private static function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
