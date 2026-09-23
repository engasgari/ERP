<?php

namespace App\Support;

use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class SimpleXlsxExporter
{
    public static function download(string $filename, array $headings, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows): void {
            $path = self::buildTempFile($headings, $rows);

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
     * @param  array<int, array{name: string, headings: array<int, string>, rows: array<int, array<int, mixed>>}>  $sheets
     */
    public static function downloadMultiSheet(string $filename, array $sheets): StreamedResponse
    {
        return response()->streamDownload(function () use ($sheets): void {
            $path = self::buildTempMultiSheetFile($sheets);

            try {
                readfile($path);
            } finally {
                @unlink($path);
            }
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public static function buildTempFile(array $headings, array $rows): string
    {
        return self::buildTempMultiSheetFile([
            [
                'name' => 'Report',
                'headings' => $headings,
                'rows' => $rows,
            ],
        ]);
    }

    /**
     * @param  array<int, array{name: string, headings: array<int, string>, rows: array<int, array<int, mixed>>}>  $sheets
     */
    public static function buildTempMultiSheetFile(array $sheets): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('افزونه zip برای خروجی اکسل فعال نیست.');
        }

        $path = tempnam(sys_get_temp_dir(), 'erp-xlsx-');

        if ($path === false) {
            throw new RuntimeException('امکان ایجاد فایل موقت اکسل وجود ندارد.');
        }

        $xlsxPath = $path . '.xlsx';
        rename($path, $xlsxPath);

        $zip = new ZipArchive();

        if ($zip->open($xlsxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('امکان ایجاد فایل اکسل وجود ندارد.');
        }

        $zip->addFromString('[Content_Types].xml', self::contentTypesXml(count($sheets)));
        $zip->addFromString('_rels/.rels', self::relsXml());
        $zip->addFromString('xl/workbook.xml', self::workbookXml($sheets));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRelsXml(count($sheets)));

        foreach ($sheets as $index => $sheet) {
            $sheetNumber = $index + 1;
            $zip->addFromString(
                'xl/worksheets/sheet' . $sheetNumber . '.xml',
                self::sheetXml($sheet['headings'] ?? [], $sheet['rows'] ?? [])
            );
        }

        $zip->addFromString('xl/styles.xml', self::stylesXml());
        $zip->close();

        return $xlsxPath;
    }

    private static function sheetXml(array $headings, array $rows): string
    {
        $sheetRows = '';

        foreach (array_merge([$headings], $rows) as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;
            $cells = '';

            foreach (array_values($row) as $columnIndex => $value) {
                $cellRef = self::columnLetter($columnIndex) . $rowNumber;
                $cells .= '<c r="' . $cellRef . '" t="inlineStr"><is><t>'
                    . self::escapeXml((string) $value)
                    . '</t></is></c>';
            }

            $sheetRows .= '<row r="' . $rowNumber . '">' . $cells . '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . $sheetRows . '</sheetData>'
            . '</worksheet>';
    }

    private static function columnLetter(int $index): string
    {
        $letter = '';
        $number = $index + 1;

        while ($number > 0) {
            $number--;
            $letter = chr(65 + ($number % 26)) . $letter;
            $number = intdiv($number, 26);
        }

        return $letter;
    }

    private static function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function contentTypesXml(int $sheetCount = 1): string
    {
        $overrides = '';
        for ($index = 1; $index <= $sheetCount; $index++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . $index . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . $overrides
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private static function relsXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML;
    }

    /**
     * @param  array<int, array{name: string, headings: array<int, string>, rows: array<int, array<int, mixed>>}>  $sheets
     */
    private static function workbookXml(array $sheets): string
    {
        $sheetNodes = '';

        foreach ($sheets as $index => $sheet) {
            $sheetNumber = $index + 1;
            $sheetName = self::escapeXml(self::sanitizeSheetName($sheet['name'] ?? ('Sheet' . $sheetNumber)));
            $sheetNodes .= '<sheet name="' . $sheetName . '" sheetId="' . $sheetNumber . '" r:id="rId' . $sheetNumber . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $sheetNodes . '</sheets>'
            . '</workbook>';
    }

    private static function workbookRelsXml(int $sheetCount): string
    {
        $relationships = '';

        for ($index = 1; $index <= $sheetCount; $index++) {
            $relationships .= '<Relationship Id="rId' . $index . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $index . '.xml"/>';
        }

        $relationships .= '<Relationship Id="rId' . ($sheetCount + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $relationships
            . '</Relationships>';
    }

    private static function sanitizeSheetName(string $name): string
    {
        $name = trim(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $name));

        if ($name === '') {
            return 'Sheet';
        }

        return mb_substr($name, 0, 31);
    }

    private static function stylesXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>
    <fills count="1"><fill><patternFill patternType="none"/></fill></fills>
    <borders count="1"><border/></borders>
    <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
    <cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>
</styleSheet>
XML;
    }
}
